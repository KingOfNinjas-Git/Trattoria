<?php

namespace App\Livewire;

use App\Models\CartItem;
use App\Models\DiningTable;
use App\Models\TableSession;
use App\Support\Ordering;
use Livewire\Attributes\Computed;
use Livewire\Component;

/** The shared cart for the table, shown as a drawer over the menu. */
class CartDrawer extends Component
{
    public int $tableId;

    public string $clientId = '';

    public bool $open = false;

    public string $notes = '';

    public function mount(int $tableId, string $clientId = ''): void
    {
        $this->tableId = $tableId;
        $this->clientId = $clientId;
    }

    private function session(): TableSession
    {
        return TableSession::forTable(DiningTable::findOrFail($this->tableId));
    }

    public function getListeners(): array
    {
        return [
            'open-cart' => 'openDrawer',
            // Another phone at the table changed the cart while the drawer is open.
            'cart-synced' => '$refresh',
        ];
    }

    #[Computed]
    public function lines()
    {
        return $this->open ? $this->session()->cartItems()->with('menuItem')->orderBy('id')->get() : collect();
    }

    #[Computed]
    public function totalCents(): int
    {
        return $this->lines->sum(fn (CartItem $l) => $l->lineTotalCents());
    }

    public function openDrawer(): void
    {
        $this->open = true;
    }

    public function close(): void
    {
        $this->open = false;
    }

    public function changeLine(int $lineId, int $delta): void
    {
        Ordering::changeQuantity($this->session(), $lineId, $delta, $this->clientId);
        $this->dispatch('cart-changed');
    }

    public function placeOrder()
    {
        $this->validate(['notes' => 'nullable|string|max:200']);

        $session = $this->session();
        $order = Ordering::placeOrder($session, $this->notes, $this->clientId);
        if (! $order) {
            $this->open = false;
            $this->dispatch('cart-changed');

            return;
        }

        return $this->redirectRoute('table.order', [
            'token' => DiningTable::find($this->tableId)->token,
            'order' => $order->id,
            'placed' => 1,
        ]);
    }

    public function render()
    {
        return view('livewire.cart-drawer');
    }
}
