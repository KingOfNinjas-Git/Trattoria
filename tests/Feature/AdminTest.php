<?php

namespace Tests\Feature;

use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_requires_login(): void
    {
        $this->get('/admin')->assertRedirect();
    }

    public function test_dashboard_and_resources_render_for_staff(): void
    {
        $this->artisan('demo:history', ['days' => 3])->assertSuccessful();
        $this->actingAs(User::first());

        foreach (['/admin', '/admin/menu-items', '/admin/categories', '/admin/dining-tables'] as $path) {
            $this->get($path)->assertOk();
        }
        $this->get('/admin/menu-items/'.MenuItem::first()->id.'/edit')->assertOk()->assertSee('Allergens');
    }

    public function test_demo_history_creates_consistent_orders(): void
    {
        $this->artisan('demo:history', ['days' => 2])->assertSuccessful();

        $orders = Order::with('items')->get();
        $this->assertGreaterThan(20, $orders->count());

        foreach ($orders as $order) {
            $this->assertSame('served', $order->status);
            $this->assertSame(
                $order->items->sum(fn ($i) => $i->price_cents * $i->quantity),
                $order->total_cents,
            );
            $this->assertTrue($order->ready_at->gt($order->created_at));
        }
    }
}
