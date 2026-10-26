<?php

namespace Tests\Feature;

use App\Filament\Widgets\SalesStats;
use App\Livewire\Kitchen;
use App\Livewire\OrderStatus;
use App\Livewire\TableBill;
use App\Livewire\TableMenu;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Models\User;
use App\Support\Ordering;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class KitchenExtrasTest extends TestCase
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

    private function placeOrder(string ...$dishes): Order
    {
        $session = TableSession::forTable($this->table);
        foreach ($dishes ?: ['Caprese'] as $dish) {
            Ordering::addToCart($session, MenuItem::where('name', $dish)->first());
        }

        return Ordering::placeOrder($session);
    }

    private function staff(): void
    {
        $this->actingAs(User::first());
    }

    public function test_a_stage_can_be_undone_and_its_timestamp_is_cleared(): void
    {
        $order = $this->placeOrder();
        $order->advance();
        $order->advance();
        $this->assertSame('ready', $order->status);
        $this->assertNotNull($order->ready_at);

        $this->assertTrue($order->retreat());
        $this->assertSame('preparing', $order->status);
        $this->assertNull($order->ready_at);

        $order->retreat();
        $this->assertFalse($order->retreat(), 'cannot go back before "received"');
        $this->assertSame('received', $order->status);
    }

    public function test_a_served_order_can_be_brought_back(): void
    {
        $order = $this->placeOrder();
        $order->update(['status' => 'served', 'served_at' => now()]);

        $this->staff();
        Livewire::test(Kitchen::class)->assertSee('Recently served')->call('undo', $order->id);

        $this->assertSame('ready', $order->fresh()->status);
        $this->assertNull($order->fresh()->served_at);
    }

    public function test_cancelled_orders_leave_the_board_and_cannot_move_on(): void
    {
        $order = $this->placeOrder();
        $this->staff();

        Livewire::test(Kitchen::class)->assertSee('Caprese')->call('cancel', $order->id)->assertDontSee('Caprese');

        $order->refresh();
        $this->assertSame('cancelled', $order->status);
        $this->assertNotNull($order->cancelled_at);
        $this->assertFalse($order->advance());
        $this->assertFalse($order->retreat());
    }

    public function test_served_orders_cannot_be_cancelled(): void
    {
        $order = $this->placeOrder();
        $order->update(['status' => 'served']);

        $this->assertFalse($order->cancel());
    }

    public function test_cancelled_orders_are_left_off_the_bill_and_the_sales_figures(): void
    {
        $kept = $this->placeOrder('Caprese');    // 9.50
        $dropped = $this->placeOrder('Arancini'); // 8.00
        $dropped->cancel();

        $this->assertSame(950, TableSession::forTable($this->table)->totalCents());

        Livewire::test(TableBill::class, ['token' => $this->table->token])
            ->assertSee('&euro;9,50', false)
            ->assertDontSee('&euro;17,50', false)
            ->assertSee('Cancelled');

        $this->staff();
        Livewire::test(SalesStats::class)->assertSee('€9,50');
    }

    public function test_the_guest_sees_a_cancelled_order_clearly(): void
    {
        $order = $this->placeOrder();
        $order->cancel();

        $this->get(route('table.order', [$this->table->token, $order->id]))
            ->assertOk()
            ->assertSee('Order cancelled')
            ->assertDontSee('Estimated wait');
    }

    public function test_dishes_can_be_ticked_off_and_the_guest_sees_progress(): void
    {
        $order = $this->placeOrder('Caprese', 'Arancini');
        $order->advance(); // preparing
        $first = $order->items->first();

        $this->staff();
        Livewire::test(Kitchen::class)->call('toggleItem', $first->id);
        $this->assertTrue($first->fresh()->is_ready);

        Livewire::test(OrderStatus::class, ['token' => $this->table->token, 'order' => $order])
            ->assertSee('1 of 2 dishes ready');

        Livewire::test(Kitchen::class)->call('toggleItem', $first->id);
        $this->assertFalse($first->fresh()->is_ready);
    }

    public function test_waiter_acknowledgement_reaches_the_guest(): void
    {
        $session = TableSession::forTable($this->table);
        $request = Ordering::requestService($session, ServiceRequest::WAITER);

        $menu = Livewire::test(TableMenu::class, ['token' => $this->table->token]);
        $menu->assertSee('Waiter requested')->assertDontSee('Waiter on the way');

        $this->staff();
        Livewire::test(Kitchen::class)->assertSee('On my way')->call('acknowledge', $request->id)->assertSee('Acknowledged');

        $menu->call('$refresh')->assertSee('Waiter on the way');
        Livewire::test(TableBill::class, ['token' => $this->table->token])->assertSee('Waiter on the way');
    }

    public function test_kitchen_can_sort_by_stage(): void
    {
        $oldest = $this->placeOrder('Caprese');
        $oldest->advance();
        $oldest->advance(); // ready
        $newest = $this->placeOrder('Arancini'); // received

        $this->staff();
        Livewire::test(Kitchen::class)->assertSeeInOrder(['Caprese', 'Arancini']);
        Livewire::test(Kitchen::class, ['sort' => 'stage'])->assertSeeInOrder(['Arancini', 'Caprese']);
    }

    public function test_estimated_wait_uses_recent_orders_and_falls_back_to_a_default(): void
    {
        $this->assertSame(15, Order::estimatedWaitMinutes(), 'default with no history');

        foreach (range(1, 4) as $i) {
            $order = $this->placeOrder();
            $order->forceFill(['created_at' => now()->subMinutes(30), 'ready_at' => now()->subMinutes(10)])->save(); // 20 min
        }

        $this->assertSame(20, Order::estimatedWaitMinutes());
    }

    public function test_estimated_wait_is_shown_while_preparing(): void
    {
        $order = $this->placeOrder();

        $this->get(route('table.order', [$this->table->token, $order->id]))
            ->assertSee('Estimated wait')
            ->assertSee('about 15 min');

        $order->forceFill(['created_at' => now()->subMinutes(40)])->save();
        $this->get(route('table.order', [$this->table->token, $order->id]))
            ->assertSee('Taking a little longer than usual');
    }

    public function test_ticket_is_for_staff_only_and_lists_the_order(): void
    {
        $order = $this->placeOrder('Caprese');

        $this->get(route('kitchen.ticket', $order))->assertRedirect(route('login'));

        $this->staff();
        $this->get(route('kitchen.ticket', $order))
            ->assertOk()
            ->assertSee('TABLE '.$this->table->number)
            ->assertSee('Caprese')
            ->assertSee('Order #'.$order->id);
    }

    public function test_admin_can_search_and_view_orders(): void
    {
        $order = $this->placeOrder('Caprese');
        $this->placeOrder('Arancini');
        $this->staff();

        $this->get('/admin/orders')->assertOk()->assertSee('Caprese');
        $this->get('/admin/orders/'.$order->id)->assertOk()->assertSee('Timeline');
        $this->get('/admin/orders/create')->assertNotFound();
    }
}
