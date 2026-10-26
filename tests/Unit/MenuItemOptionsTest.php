<?php

namespace Tests\Unit;

use App\Models\MenuItem;
use PHPUnit\Framework\TestCase;

class MenuItemOptionsTest extends TestCase
{
    private function item(): MenuItem
    {
        return new MenuItem([
            'price_cents' => 1000,
            'options' => [
                ['name' => 'Size', 'type' => 'single', 'required' => true, 'choices' => [
                    ['name' => 'Small', 'price_cents' => 0],
                    ['name' => 'Large', 'price_cents' => 300],
                ]],
                ['name' => 'Extras', 'type' => 'multiple', 'required' => false, 'choices' => [
                    ['name' => 'Cheese', 'price_cents' => 100],
                    ['name' => 'Ham', 'price_cents' => 200],
                ]],
            ],
        ]);
    }

    public function test_price_adds_the_chosen_extras(): void
    {
        $item = $this->item();

        $this->assertSame(1000, $item->unitPriceCents([0 => [0]]));
        $this->assertSame(1600, $item->unitPriceCents([0 => [1], 1 => [0, 1]]));
    }

    public function test_selection_is_cleaned_and_validated(): void
    {
        $item = $this->item();

        $this->assertSame([0 => [1], 1 => [0, 1]], $item->normaliseSelection([0 => ['1'], 1 => [1, 0, 1]]));
        $this->assertNull($item->normaliseSelection([]), 'required group missing');
        $this->assertNull($item->normaliseSelection([0 => [0, 1]]), 'two choices in a single group');
        $this->assertSame([0 => [0]], $item->normaliseSelection([0 => [0], 1 => [9]]), 'unknown choices dropped');
    }

    public function test_options_text_lists_choice_names(): void
    {
        $this->assertSame('Large, Ham', $this->item()->optionsText([0 => [1], 1 => [1]]));
        $this->assertNull($this->item()->optionsText([]));
    }

    public function test_dish_without_options_accepts_an_empty_selection(): void
    {
        $plain = new MenuItem(['price_cents' => 500]);

        $this->assertSame([], $plain->normaliseSelection([]));
        $this->assertSame(500, $plain->unitPriceCents([]));
        $this->assertFalse($plain->hasOptions());
    }
}
