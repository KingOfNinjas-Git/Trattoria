<?php

namespace Tests\Feature;

use App\Models\DiningTable;
use App\Models\Order;
use App\Models\User;
use App\Support\DemoMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class DemoModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_nothing_changes_when_demo_mode_is_off(): void
    {
        $table = DiningTable::create(['number' => 1, 'name' => 'Table 1', 'seats' => 2]);

        $this->get(route('table.menu', $table->token))->assertOk()->assertDontSee('Portfolio demo');
        $this->assertTrue((new User)->canAccessPanel(\Filament\Facades\Filament::getPanel('admin')));
    }

    public function test_demo_user_cannot_open_the_admin_panel_but_staff_can(): void
    {
        config(['trattoria.demo' => true]);
        $panel = \Filament\Facades\Filament::getPanel('admin');

        $this->assertFalse(DemoMode::staffUser()->canAccessPanel($panel));
        $this->assertTrue(User::forceCreate(['name' => 'Owner', 'email' => 'owner@example.com', 'password' => 'x'])->canAccessPanel($panel));
    }

    public function test_the_notice_shows_and_old_orders_are_cleared_once_an_hour(): void
    {
        config(['trattoria.demo' => true]);
        $table = DiningTable::create(['number' => 1, 'name' => 'Table 1', 'seats' => 2]);
        $this->get(route('table.menu', $table->token))->assertSee('Portfolio demo');

        $old = Order::forceCreate(['dining_table_id' => $table->id, 'status' => 'received', 'created_at' => now()->subHours(8)]);
        $fresh = Order::forceCreate(['dining_table_id' => $table->id, 'status' => 'received']);

        Cache::forget('demo-cleanup');
        DemoMode::cleanUp();

        $this->assertNull(Order::find($old->id));
        $this->assertNotNull(Order::find($fresh->id));

        $again = Order::forceCreate(['dining_table_id' => $table->id, 'status' => 'received', 'created_at' => now()->subHours(9)]);
        DemoMode::cleanUp(); // within the hour: does nothing
        $this->assertNotNull(Order::find($again->id));
    }
}
