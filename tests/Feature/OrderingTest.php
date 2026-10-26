<?php

namespace Tests\Feature;

use App\Events\TableUpdated;
use App\Livewire\CartDrawer;
use App\Livewire\DishModal;
use App\Livewire\TableBill;
use App\Livewire\TableMenu;
use App\Models\CartItem;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Support\Ordering;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class OrderingTest extends TestCase
{
    use RefreshDatabase;

    private DiningTable $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->withoutVite();
        $this->table = DiningTable::orderBy('number')->first();
    }

    private function menu()
    {
        return Livewire::test(TableMenu::class, ['token' => $this->table->token]);
    }

    private function modal()
    {
        return Livewire::test(DishModal::class, ['tableId' => $this->table->id, 'clientId' => 'test']);
    }

    private function drawer()
    {
        return Livewire::test(CartDrawer::class, ['tableId' => $this->table->id, 'clientId' => 'test']);
    }

    private function dish(string $name): MenuItem
    {
        return MenuItem::where('name', $name)->firstOrFail();
    }

    private function sendOrder(?string $notes = null): void
    {
        $this->drawer()->call('openDrawer')->set('notes', $notes ?? '')->call('placeOrder');
    }

    public function test_guest_can_add_simple_dishes_and_send_the_order(): void
    {
        $bruschetta = $this->dish('Bruschetta al Pomodoro');
        $tiramisu = $this->dish('Tiramisù');

        $menu = $this->menu()
            ->call('quickAdjust', $bruschetta->id, 2)
            ->call('quickAdjust', $tiramisu->id, 1)
            ->assertSet('cartCount', 3)
            ->assertSet('cartTotalCents', 650 * 2 + 700);

        $this->drawer()
            ->call('openDrawer')
            ->set('notes', 'no basil')
            ->call('placeOrder')
            ->assertRedirect();

        $order = Order::with('items')->first();
        $this->assertSame($this->table->id, $order->dining_table_id);
        $this->assertSame(650 * 2 + 700, $order->total_cents);
        $this->assertSame('received', $order->status);
        $this->assertSame('no basil', $order->notes);
        $this->assertCount(2, $order->items);
        $this->assertSame(0, CartItem::count(), 'cart is emptied once the order is sent');

        $menu->call('syncCart')->assertSet('cartCount', 0)->assertSet('counts', []);
    }

    public function test_menu_page_counts_follow_the_shared_cart(): void
    {
        $caprese = $this->dish('Caprese');

        $this->menu()
            ->call('quickAdjust', $caprese->id, 3)
            ->assertSet('counts', [$caprese->id => 3])
            ->call('quickAdjust', $caprese->id, -1)
            ->assertSet('counts', [$caprese->id => 2])
            ->call('quickAdjust', $caprese->id, -5)
            ->assertSet('counts', []);
    }

    public function test_quick_adjust_ignores_dishes_with_options_and_unknown_dishes(): void
    {
        $this->menu()
            ->call('quickAdjust', $this->dish('Margherita')->id, 1)
            ->call('quickAdjust', 99999, 1)
            ->assertSet('cartCount', 0);

        $this->assertSame(0, CartItem::count());
    }

    public function test_options_change_the_price_and_are_saved_on_the_order(): void
    {
        $pizza = $this->dish('Margherita'); // base EUR 9.00

        $this->modal()
            ->call('open', $pizza->id)
            ->assertSet('choices', [0 => [0]]) // required size pre-selected
            ->call('toggleChoice', 0, 1)         // Large (+4.00)
            ->call('toggleChoice', 1, 0)         // Extra mozzarella (+1.50)
            ->call('toggleChoice', 1, 2)         // Spicy salame (+1.50)
            ->assertSet('choices', [0 => [1], 1 => [0, 2]])
            ->call('add')
            ->assertDispatched('cart-changed');

        $this->sendOrder();

        $line = Order::first()->items->first();
        $this->assertSame(900 + 400 + 150 + 150, $line->price_cents);
        $this->assertSame('Large (40cm), Extra mozzarella, Spicy salame', $line->options_text);
    }

    public function test_popup_is_really_closed_after_adding_a_dish(): void
    {
        $this->modal()
            ->call('open', $this->dish('Margherita')->id)
            ->assertSeeHtml('role="dialog"')
            ->call('add')
            ->assertSet('selectedId', null)
            ->assertDontSeeHtml('role="dialog"');
    }

    public function test_modal_price_follows_quantity_and_choices(): void
    {
        $this->modal()
            ->call('open', $this->dish('Margherita')->id)
            ->assertSet('qty', 1)
            ->call('toggleChoice', 0, 1)
            ->call('stepQty', 2)
            ->assertSet('qty', 3)
            ->assertSee('&euro;39,00', false) // (9 + 4) x 3
            ->call('stepQty', -10)
            ->assertSet('qty', 1);
    }

    public function test_single_choice_groups_hold_only_one_choice_and_multi_groups_toggle(): void
    {
        $this->modal()
            ->call('open', $this->dish('Margherita')->id)
            ->call('toggleChoice', 0, 1)
            ->call('toggleChoice', 0, 0)
            ->assertSet('choices.0', [0])
            ->call('toggleChoice', 1, 1)
            ->call('toggleChoice', 1, 1)
            ->assertSet('choices.1', []);
    }

    public function test_required_options_must_be_chosen(): void
    {
        $pizza = $this->dish('Margherita');
        $session = TableSession::forTable($this->table);

        $this->assertFalse(Ordering::addToCart($session, $pizza, []), 'size is required');
        $this->assertFalse(Ordering::addToCart($session, $pizza, [0 => [0, 1]]), 'single group takes one choice');
        $this->assertFalse(Ordering::addToCart($session, $pizza, [0 => [9]]), 'unknown choice');
        $this->assertTrue(Ordering::addToCart($session, $pizza, [0 => [0]]));
        $this->assertSame(1, CartItem::count());
    }

    public function test_identical_lines_merge_and_different_options_stay_separate(): void
    {
        $pizza = $this->dish('Margherita');
        $session = TableSession::forTable($this->table);

        Ordering::addToCart($session, $pizza, [0 => [0]]);
        Ordering::addToCart($session, $pizza, [0 => [0]]);
        Ordering::addToCart($session, $pizza, [0 => [1]]);

        $this->assertSame(2, CartItem::count());
        $this->assertSame(3, (int) CartItem::sum('quantity'));
    }

    public function test_unavailable_dishes_cannot_be_ordered(): void
    {
        $item = $this->dish('Bruschetta al Pomodoro');
        $item->update(['is_available' => false]);

        $this->menu()->call('quickAdjust', $item->id, 1);

        $this->assertSame(0, CartItem::count());
    }

    public function test_cart_drawer_changes_quantities_and_removes_empty_lines(): void
    {
        $session = TableSession::forTable($this->table);
        Ordering::addToCart($session, $this->dish('Bruschetta al Pomodoro'));
        $line = CartItem::first();

        $drawer = $this->drawer()->call('openDrawer')->call('changeLine', $line->id, 1);
        $this->assertSame(2, $line->fresh()->quantity);

        $drawer->call('changeLine', $line->id, -1)->call('changeLine', $line->id, -1)->assertSee('Your order is empty.');
        $this->assertSame(0, CartItem::count());
    }

    public function test_two_phones_at_the_same_table_share_one_cart(): void
    {
        $phoneA = $this->menu();
        $phoneB = $this->menu();

        $phoneA->call('quickAdjust', $this->dish('Caprese')->id, 1);
        $this->assertSame(0, $phoneB->get('cartCount'), 'phone B has not synced yet');

        $phoneB->call('syncFromOthers')->assertSet('cartCount', 1)->assertDispatched('cart-synced');
        $phoneB->call('quickAdjust', $this->dish('Arancini')->id, 1);
        $phoneA->call('syncFromOthers')->assertSet('cartCount', 2);
    }

    public function test_different_tables_have_separate_carts(): void
    {
        $other = DiningTable::orderBy('number')->skip(1)->first();

        $this->menu()->call('quickAdjust', $this->dish('Caprese')->id, 1);

        $this->assertSame(0, Livewire::test(TableMenu::class, ['token' => $other->token])->get('cartCount'));
    }

    public function test_empty_cart_does_not_create_an_order(): void
    {
        $this->drawer()->call('openDrawer')->call('placeOrder');

        $this->assertSame(0, Order::count());
    }

    public function test_order_keeps_the_price_it_was_placed_at(): void
    {
        $item = $this->dish('Caprese');
        $this->menu()->call('quickAdjust', $item->id, 1);
        $this->sendOrder();

        $item->update(['price_cents' => 5000]);

        $this->assertSame(950, Order::first()->items->first()->price_cents);
        $this->assertSame(950, Order::first()->total_cents);
    }

    public function test_session_is_reused_until_closed_or_idle(): void
    {
        $first = TableSession::forTable($this->table);
        $this->assertSame($first->id, TableSession::forTable($this->table)->id);

        $first->close();
        $second = TableSession::forTable($this->table);
        $this->assertNotSame($first->id, $second->id);

        $second->forceFill(['last_activity_at' => now()->subHours(TableSession::IDLE_HOURS + 1)])->save();
        $this->assertNotSame($second->id, TableSession::forTable($this->table)->id);
    }

    public function test_actions_on_a_closed_session_do_nothing(): void
    {
        $session = TableSession::forTable($this->table);
        $session->close();

        $this->assertFalse(Ordering::addToCart($session->fresh(), $this->dish('Caprese')));
        $this->assertNull(Ordering::placeOrder($session->fresh()));
    }

    public function test_menu_page_reloads_onto_the_new_session_after_staff_close_the_table(): void
    {
        $component = $this->menu();
        TableSession::forTable($this->table)->close();

        $component->call('$refresh')->assertRedirect(route('table.menu', $this->table->token));
    }

    public function test_broadcasts_carry_the_page_id_so_a_page_can_skip_its_own_events(): void
    {
        Event::fake([TableUpdated::class]);

        $this->menu()->call('quickAdjust', $this->dish('Caprese')->id, 1);

        Event::assertDispatched(
            TableUpdated::class,
            fn ($e) => $e->kind === 'cart' && strlen((string) $e->origin) === 12,
        );
    }

    public function test_calling_a_waiter_creates_one_open_request(): void
    {
        $this->menu()->call('callWaiter')->call('callWaiter');

        $this->assertSame(1, ServiceRequest::where('type', 'waiter')->whereNull('resolved_at')->count());
    }

    public function test_bill_page_totals_every_order_of_the_table(): void
    {
        $this->menu()->call('quickAdjust', $this->dish('Caprese')->id, 1);
        $this->sendOrder();
        $this->menu()->call('quickAdjust', $this->dish('Arancini')->id, 1);
        $this->sendOrder();

        Livewire::test(TableBill::class, ['token' => $this->table->token])
            ->assertSee('&euro;17,50', false)
            ->call('requestBill');

        $this->assertSame(1, ServiceRequest::where('type', 'bill')->count());
    }

    public function test_order_status_page_belongs_to_its_table(): void
    {
        $this->menu()->call('quickAdjust', $this->dish('Caprese')->id, 1);
        $this->sendOrder();
        $order = Order::first();
        $other = DiningTable::orderBy('number')->skip(1)->first();

        $this->get(route('table.order', [$this->table->token, $order->id]))->assertOk()->assertSee('Order #'.$order->id);
        $this->get(route('table.order', [$other->token, $order->id]))->assertNotFound();
    }
}
