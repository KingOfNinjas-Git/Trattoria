<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->withoutVite();
    }

    private function menuUrl(): string
    {
        return route('table.menu', DiningTable::orderBy('number')->first()->token);
    }

    public function test_every_dish_shows_name_price_and_description(): void
    {
        $page = $this->get($this->menuUrl())->assertOk();

        foreach (MenuItem::all() as $item) {
            $page->assertSee($item->name) // escaped, so apostrophes match
                ->assertSee('&euro;'.$item->price, false);
        }

        $page->assertSee('San Marzano tomato, fior di latte mozzarella and fresh basil.');
    }

    public function test_menu_uses_english_category_names_with_italian_subtitles(): void
    {
        $this->get($this->menuUrl())
            ->assertSee('Starters')
            ->assertSee('Main Courses')
            ->assertSee('Desserts')
            ->assertSee('Antipasti')
            ->assertDontSee('Aggiungi');
    }

    public function test_menu_contains_no_emoji(): void
    {
        $html = $this->get($this->menuUrl())->getContent();

        $this->assertSame(0, preg_match('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', $html));
    }

    public function test_dish_photos_and_allergens_are_shown(): void
    {
        $this->get($this->menuUrl())
            ->assertSee('images/menu/margherita.webp', false)
            ->assertSee('Contains: Gluten, Dairy');
    }

    public function test_every_seeded_photo_exists_on_disk(): void
    {
        foreach (MenuItem::whereNotNull('image')->pluck('image') as $file) {
            $this->assertFileExists(public_path('images/menu/'.$file));
        }
    }

    public function test_photo_credits_page_lists_the_photos(): void
    {
        $this->get(route('credits'))->assertOk()->assertSee('Photo credits')->assertSee('Margherita');
    }

    public function test_root_redirects_to_the_first_table_menu(): void
    {
        $this->get('/')->assertRedirect($this->menuUrl());
    }

    public function test_unknown_table_token_shows_a_friendly_page_not_a_stack_trace(): void
    {
        $this->get(route('table.menu', 'nope'))
            ->assertNotFound()
            ->assertSee('We could not find that page')
            ->assertSee('scan the QR code on your table again')
            ->assertDontSee('Illuminate');

        $this->get(route('lang', 'it'));
        $this->get(route('table.menu', 'nope'))->assertNotFound()->assertSee('Non troviamo questa pagina');
    }

    public function test_every_error_page_renders(): void
    {
        foreach ([404, 419, 500, 503] as $code) {
            $html = view('errors.'.$code)->render();

            $this->assertStringContainsString((string) $code, $html);
            $this->assertStringContainsString('Back to the menu', $html);
        }
    }

    public function test_menu_can_be_switched_to_italian_and_portuguese(): void
    {
        $this->get(route('lang', 'it'));
        $this->get($this->menuUrl())
            ->assertSee('Chiama un cameriere')
            ->assertSee('Pomodoro San Marzano, mozzarella fior di latte e basilico fresco.')
            ->assertSee('Secondi');

        $this->get(route('lang', 'pt'));
        $this->get($this->menuUrl())
            ->assertSee('Chamar um empregado')
            ->assertSee('Entradas')
            ->assertSee('Tomate San Marzano');
    }

    public function test_unsupported_language_is_rejected(): void
    {
        $this->get(route('lang', 'xx'))->assertNotFound();
    }

    public function test_every_dish_has_translations_for_both_languages(): void
    {
        foreach (MenuItem::all() as $item) {
            $this->assertNotEmpty(data_get($item->translations, 'it.description'), $item->name.' lacks Italian');
            $this->assertNotEmpty(data_get($item->translations, 'pt.description'), $item->name.' lacks Portuguese');
        }
        foreach (Category::all() as $category) {
            $this->assertNotEmpty(data_get($category->translations, 'it.name'));
            $this->assertNotEmpty(data_get($category->translations, 'pt.name'));
        }
    }
}
