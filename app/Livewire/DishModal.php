<?php

namespace App\Livewire;

use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\TableSession;
use App\Support\Ordering;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

/** The dish detail popup: large photo, allergens and option choices. */
class DishModal extends Component
{
    public int $tableId;

    public string $clientId = '';

    public ?int $selectedId = null;

    /** Option choices made: [groupIndex => [choiceIndex, ...]]. */
    public array $choices = [];

    public int $qty = 1;

    public function mount(int $tableId, string $clientId = ''): void
    {
        $this->tableId = $tableId;
        $this->clientId = $clientId;
    }

    private function session(): TableSession
    {
        return TableSession::forTable(DiningTable::findOrFail($this->tableId));
    }

    #[Computed]
    public function selected(): ?MenuItem
    {
        return $this->selectedId ? MenuItem::find($this->selectedId) : null;
    }

    #[Computed]
    public function priceCents(): int
    {
        $item = $this->selected;

        return $item ? $item->unitPriceCents($item->normaliseSelection($this->choices) ?? $this->choices) * $this->qty : 0;
    }

    #[On('open-dish')]
    public function open(int $id): void
    {
        $item = MenuItem::find($id);
        if (! $item) {
            return;
        }

        $this->selectedId = $id;
        $this->qty = 1;
        $this->choices = [];
        unset($this->selected, $this->priceCents);

        // Pre-select the first choice of required single-choice groups.
        foreach ($item->options ?? [] as $g => $group) {
            if (($group['type'] ?? 'single') === 'single' && ! empty($group['required'])) {
                $this->choices[$g] = [0];
            }
        }
    }

    public function close(): void
    {
        $this->selectedId = null;
        // `selected` is cached for the whole request; without this the popup would be rendered
        // once more with the dish we just closed.
        unset($this->selected, $this->priceCents);
    }

    public function toggleChoice(int $group, int $choice): void
    {
        $def = $this->selected?->options[$group] ?? null;
        if (! $def || ! isset($def['choices'][$choice])) {
            return;
        }

        $current = $this->choices[$group] ?? [];

        if (($def['type'] ?? 'single') === 'single') {
            // Optional single groups can be un-picked by tapping again.
            $this->choices[$group] = ($current === [$choice] && empty($def['required'])) ? [] : [$choice];
        } else {
            $this->choices[$group] = in_array($choice, $current, true)
                ? array_values(array_diff($current, [$choice]))
                : [...$current, $choice];
        }
    }

    public function stepQty(int $delta): void
    {
        $this->qty = max(1, min(20, $this->qty + $delta));
    }

    public function add(): void
    {
        $item = $this->selected;
        if (! $item) {
            return;
        }

        if (! Ordering::addToCart($this->session(), $item, $this->choices, $this->qty, $this->clientId)) {
            $this->dispatch('toast', message: __('Please choose the required options.'));

            return;
        }

        $this->close();
        $this->dispatch('cart-changed');
        $this->dispatch('toast', message: __('Added to your order'));
    }

    public function render()
    {
        return view('livewire.dish-modal');
    }
}
