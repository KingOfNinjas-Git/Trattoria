<?php

namespace App\Livewire;

use App\Models\CartItem;
use App\Models\Category;
use App\Models\DiningTable;
use App\Models\MenuItem;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Support\Ordering;
use Illuminate\Support\Str;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Renderless;
use Livewire\Component;

/**
 * The menu page. Cart changes made here do not re-render the (large) menu: the screen updates
 * optimistically in the browser, and the cart numbers below are simply re-synced from the server.
 * The dish popup and the cart drawer are separate components (DishModal, CartDrawer).
 */
#[Layout('layouts.app')]
class TableMenu extends Component
{
    public DiningTable $table;

    public TableSession $session;

    /** Broadcast channel this page subscribed to. Stays fixed even if staff close the table meanwhile. */
    public string $channelToken = '';

    /** Identifies this page load, so it can ignore the broadcasts it caused itself. */
    public string $clientId = '';

    /** menu_item_id => total quantity in the shared cart (all option variants). */
    public array $counts = [];

    public int $cartCount = 0;

    public int $cartTotalCents = 0;

    public function mount(string $token): void
    {
        $this->table = DiningTable::where('token', $token)->firstOrFail();
        $this->session = TableSession::forTable($this->table);
        $this->channelToken = $this->session->token;
        $this->clientId = Str::random(12);
        $this->loadCart();
    }

    private function loadCart(): void
    {
        $lines = $this->session->cartItems()->with('menuItem')->get();

        $this->counts = $lines->groupBy('menu_item_id')->map->sum('quantity')->all();
        $this->cartCount = (int) $lines->sum('quantity');
        $this->cartTotalCents = $lines->sum(fn (CartItem $l) => $l->lineTotalCents());
    }

    /** Re-read the cart after the dish popup or cart drawer changed it. */
    #[On('cart-changed')]
    #[Renderless]
    public function syncCart(): void
    {
        $this->loadCart();
    }

    /** Called when another phone at the table changed the cart. */
    #[Renderless]
    public function syncFromOthers(): void
    {
        $this->loadCart();
        $this->dispatch('cart-synced'); // lets an open cart drawer refresh itself
    }

    #[Computed]
    public function categories()
    {
        return Category::with('items')->orderBy('sort_order')->get();
    }

    #[Computed]
    public function waiterRequested(): bool
    {
        return $this->session->serviceRequests()
            ->where('type', ServiceRequest::WAITER)->whereNull('resolved_at')->exists();
    }

    /** A waiter has seen the request and is on the way. */
    #[Computed]
    public function waiterAcknowledged(): bool
    {
        return $this->session->serviceRequests()
            ->where('type', ServiceRequest::WAITER)->whereNull('resolved_at')->whereNotNull('acknowledged_at')->exists();
    }

    #[Computed]
    public function hasOrders(): bool
    {
        return $this->session->orders()->exists();
    }

    /**
     * Stepper on dishes without options. The browser batches quick taps into one call, so this
     * receives a net change (for example +3, or -1). Dishes with options open the popup instead.
     */
    #[Renderless]
    public function quickAdjust(int $id, int $delta): void
    {
        $delta = max(-20, min(20, $delta));
        $item = MenuItem::find($id);

        if ($item && ! $item->hasOptions() && $delta !== 0) {
            if ($delta > 0) {
                Ordering::addToCart($this->session, $item, [], $delta, $this->clientId);
            } else {
                $line = $this->session->cartItems()->where('menu_item_id', $id)->where('selection_key', '')->first();
                if ($line) {
                    Ordering::changeQuantity($this->session, $line->id, $delta, $this->clientId);
                }
            }
        }

        $this->loadCart();
    }

    public function callWaiter(): void
    {
        Ordering::requestService($this->session, ServiceRequest::WAITER, $this->clientId);
        $this->dispatch('toast', message: __('A waiter is on the way.'));
    }

    public function render()
    {
        // Staff closed this table (or it sat idle): reload onto the fresh session and channel.
        if (TableSession::forTable($this->table)->id !== $this->session->id) {
            $this->redirectRoute('table.menu', $this->table->token);
        }

        return view('livewire.table-menu');
    }
}
