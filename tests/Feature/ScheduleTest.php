<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\TableSession;
use App\Models\User;
use App\Support\Ordering;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->withoutVite();
    }

    private function at(string $time): Carbon
    {
        return Carbon::parse('2026-10-26 '.$time);
    }

    public function test_a_section_without_a_schedule_is_always_open(): void
    {
        $this->assertTrue(Category::first()->isOpenNow($this->at('03:00')));
    }

    public function test_same_day_window(): void
    {
        $cat = new Category(['available_from' => '12:00:00', 'available_until' => '15:00:00']);

        $this->assertFalse($cat->isOpenNow($this->at('11:59')));
        $this->assertTrue($cat->isOpenNow($this->at('12:00')));
        $this->assertTrue($cat->isOpenNow($this->at('14:59')));
        $this->assertFalse($cat->isOpenNow($this->at('15:00')));
        $this->assertSame('12:00 - 15:00', $cat->scheduleLabel());
    }

    public function test_window_that_runs_past_midnight(): void
    {
        $cat = new Category(['available_from' => '18:00:00', 'available_until' => '02:00:00']);

        $this->assertTrue($cat->isOpenNow($this->at('23:30')));
        $this->assertTrue($cat->isOpenNow($this->at('01:00')));
        $this->assertFalse($cat->isOpenNow($this->at('02:00')));
        $this->assertFalse($cat->isOpenNow($this->at('12:00')));
    }

    public function test_a_closed_section_is_shown_but_cannot_be_ordered(): void
    {
        Carbon::setTestNow($this->at('11:00'));
        Category::where('name', 'Pizzas')->update(['available_from' => '18:00', 'available_until' => '23:00']);
        $table = DiningTable::orderBy('number')->first();
        $pizza = MenuItem::where('name', 'Margherita')->first();
        $starter = MenuItem::where('name', 'Caprese')->first();

        $this->get(route('table.menu', $table->token))
            ->assertSee('Pizzas')
            ->assertSee('Served 18:00 - 23:00')
            ->assertSee('Not served right now');

        $session = TableSession::forTable($table);
        $this->assertFalse(Ordering::addToCart($session, $pizza, [0 => [0]]), 'pizza section is closed');
        $this->assertTrue(Ordering::addToCart($session, $starter), 'starters are served all day');

        Carbon::setTestNow($this->at('19:00'));
        $this->assertTrue(Ordering::addToCart($session, $pizza->fresh(), [0 => [0]]));

        Carbon::setTestNow();
    }

    public function test_a_dish_that_closes_while_in_the_cart_is_not_sent(): void
    {
        Carbon::setTestNow($this->at('19:00'));
        Category::where('name', 'Pizzas')->update(['available_from' => '18:00', 'available_until' => '23:00']);
        $table = DiningTable::orderBy('number')->first();
        $session = TableSession::forTable($table);
        Ordering::addToCart($session, MenuItem::where('name', 'Margherita')->first(), [0 => [0]]);
        Ordering::addToCart($session, MenuItem::where('name', 'Caprese')->first());

        Carbon::setTestNow($this->at('23:30')); // the pizza section has closed
        $order = Ordering::placeOrder($session);

        $this->assertCount(1, $order->items, 'only the starter is sent');
        $this->assertSame('Caprese', $order->items->first()->name);

        Carbon::setTestNow();
    }

    public function test_admin_can_set_a_schedule_and_reorder(): void
    {
        $this->actingAs(User::first());
        $category = Category::first();

        $this->get('/admin/categories/'.$category->id.'/edit')->assertOk()->assertSee('Opening hours for this section');
        $this->get('/admin/categories')->assertOk()->assertSee('All day');
        $this->get('/admin/menu-items')->assertOk();
    }

    public function test_app_timezone_is_configurable(): void
    {
        $this->assertSame(env('APP_TIMEZONE', 'UTC'), config('app.timezone'));
    }
}
