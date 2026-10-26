<?php

namespace Tests\Feature;

use App\Livewire\Kitchen;
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

class KitchenTest extends TestCase
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

    private function placeOrder(string $dish = 'Caprese', ?string $notes = null): Order
    {
        $session = TableSession::forTable($this->table);
        Ordering::addToCart($session, MenuItem::where('name', $dish)->first());

        return Ordering::placeOrder($session, $notes);
    }

    public function test_kitchen_requires_login(): void
    {
        $this->get(route('kitchen'))->assertRedirect(route('login'));
    }

    public function test_qr_pages_require_login(): void
    {
        $this->get(route('qr.all'))->assertRedirect(route('login'));
        $this->actingAs(User::first())->get(route('qr.all'))->assertOk()->assertSee('Scan to order');
    }

    public function test_kitchen_shows_orders_with_notes_and_options(): void
    {
        $session = TableSession::forTable($this->table);
        Ordering::addToCart($session, MenuItem::where('name', 'Margherita')->first(), [0 => [1], 1 => [0]]);
        Ordering::placeOrder($session, 'no basil');

        $this->actingAs(User::first());
        Livewire::test(Kitchen::class)
            ->assertSee('Table '.$this->table->number)
            ->assertSee('Margherita')
            ->assertSee('Large (40cm), Extra mozzarella')
            ->assertSee('no basil');
    }

    public function test_advancing_an_order_stamps_each_stage_and_stops_at_served(): void
    {
        $order = $this->placeOrder();
        $this->actingAs(User::first());

        $kitchen = Livewire::test(Kitchen::class);
        $kitchen->call('advance', $order->id);
        $this->assertSame('preparing', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->preparing_at);

        $kitchen->call('advance', $order->id);
        $this->assertNotNull($order->fresh()->ready_at);

        $kitchen->call('advance', $order->id);
        $this->assertSame('served', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->served_at);

        $kitchen->call('advance', $order->id); // no further stage
        $this->assertSame('served', $order->fresh()->status);
    }

    public function test_served_orders_leave_the_board(): void
    {
        $order = $this->placeOrder();
        $order->update(['status' => 'served']);

        $this->actingAs(User::first());
        Livewire::test(Kitchen::class)->assertDontSee('Caprese');
    }

    public function test_service_requests_show_and_can_be_resolved(): void
    {
        $session = TableSession::forTable($this->table);
        $request = Ordering::requestService($session, ServiceRequest::WAITER);

        $this->actingAs(User::first());
        Livewire::test(Kitchen::class)
            ->assertSee('needs a waiter')
            ->call('resolveRequest', $request->id)
            ->assertDontSee('needs a waiter');
    }

    public function test_closing_a_table_ends_its_session_and_clears_requests(): void
    {
        $session = TableSession::forTable($this->table);
        $this->placeOrder();
        Ordering::requestService($session, ServiceRequest::BILL);

        $this->actingAs(User::first());
        Livewire::test(Kitchen::class)
            ->assertSee('wants the bill')
            ->call('closeTable', $session->id)
            ->assertDontSee('wants the bill');

        $this->assertNotNull($session->fresh()->closed_at);
        $this->assertNotSame($session->id, TableSession::forTable($this->table)->id);
    }

    public function test_kitchen_can_mark_a_dish_sold_out_and_back(): void
    {
        $item = MenuItem::where('name', 'Caprese')->first();
        $this->actingAs(User::first());

        Livewire::test(Kitchen::class, ['tab' => 'menu'])->call('toggleAvailability', $item->id);
        $this->assertFalse($item->fresh()->is_available);

        $this->get(route('table.menu', $this->table->token))->assertSee('Sold out');

        Livewire::test(Kitchen::class, ['tab' => 'menu'])->call('toggleAvailability', $item->id);
        $this->assertTrue($item->fresh()->is_available);
    }
}
