<?php

namespace Tests\Feature;

use App\Filament\Resources\MenuItems\Pages\EditMenuItem;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\User;
use App\Support\DemoMode;
use Filament\Actions\DeleteAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

    public function test_the_demo_staff_account_can_open_the_kitchen_and_the_admin_panel(): void
    {
        config(['trattoria.demo' => true]);
        $panel = \Filament\Facades\Filament::getPanel('admin');
        $staff = DemoMode::staffUser();

        $this->assertTrue($staff->canAccessPanel($panel));
        $this->actingAs($staff)->get('/kitchen')->assertOk();
        $this->actingAs($staff)->get('/admin')->assertOk();
    }

    public function test_nothing_can_be_deleted_in_the_demo_but_staff_can_delete_on_a_real_site(): void
    {
        $this->seed(\Database\Seeders\DatabaseSeeder::class);
        $item = MenuItem::first();
        $this->actingAs(User::first());
        \Filament\Facades\Filament::setCurrentPanel('admin');

        config(['trattoria.demo' => true]);
        $page = Livewire::test(EditMenuItem::class, ['record' => $item->getRouteKey()])->mountAction(DeleteAction::class);
        $this->assertSame('Deleting is turned off in the live demo', $page->instance()->getMountedAction()->getModalHeading());
        $page->callMountedAction();
        $this->assertNotNull(MenuItem::find($item->id));

        config(['trattoria.demo' => false]);
        Livewire::test(EditMenuItem::class, ['record' => $item->getRouteKey()])
            ->callAction(DeleteAction::class);
        $this->assertNull(MenuItem::find($item->id));
    }

    public function test_every_page_explains_that_the_staff_areas_are_open_only_because_it_is_a_demo(): void
    {
        config(['trattoria.demo' => true]);
        $table = DiningTable::create(['number' => 1, 'name' => 'Table 1', 'seats' => 2]);
        $staff = DemoMode::staffUser();

        $this->get(route('table.menu', $table->token))->assertSee('Kitchen board')->assertSee('Admin panel')->assertSee('secured behind a login');
        $this->actingAs($staff)->get('/kitchen')->assertSee('secured behind a login');
        $this->actingAs($staff)->get('/admin')->assertSee('Admin panel')->assertSee('secured behind a login');
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
