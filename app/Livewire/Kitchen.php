<?php

namespace App\Livewire;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Support\Realtime;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class Kitchen extends Component
{
    #[Url]
    public string $tab = 'orders';

    /** "age": oldest first. "stage": received first, then preparing, then ready (oldest first in each). */
    #[Url]
    public string $sort = 'age';

    /** Highest order id seen so far; lets the polling fallback detect new orders too. */
    public int $lastSeenId = 0;

    public function getListeners(): array
    {
        return config('trattoria.realtime') === 'reverb' ? ['echo-private:kitchen,.updated' => 'onUpdate'] : [];
    }

    /** Pushed by Reverb whenever a cart, order or service request changes. */
    public function onUpdate(array $payload = []): void
    {
        if (in_array($payload['kind'] ?? '', ['order.placed', 'service'], true)) {
            $this->dispatch('new-order', kind: $payload['kind']);
        }
    }

    private function notifyGuests(?Order $order): void
    {
        $order?->loadMissing('session');
        if ($order?->session) {
            Realtime::table($order->session->token, 'order.status');
        }
    }

    public function advance(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $order->advance();
        $this->notifyGuests($order);
    }

    /** Undo the last stage after a mis-tap. */
    public function undo(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $order->retreat();
        $this->notifyGuests($order);
    }

    public function cancel(int $orderId): void
    {
        $order = Order::findOrFail($orderId);
        $order->cancel();
        $this->notifyGuests($order);
    }

    /** Tick a single dish off (or back on) before the whole order is ready. */
    public function toggleItem(int $itemId): void
    {
        $item = OrderItem::with('order.session')->findOrFail($itemId);
        $item->update(['is_ready' => ! $item->is_ready]);
        $this->notifyGuests($item->order);
    }

    /** A waiter has seen the request: the guest's phone says so. */
    public function acknowledge(int $id): void
    {
        $request = ServiceRequest::with('session')->findOrFail($id);
        $request->update(['acknowledged_at' => now()]);

        if ($request->session) {
            Realtime::table($request->session->token, 'service');
        }
    }

    public function resolveRequest(int $id): void
    {
        $request = ServiceRequest::with('session')->findOrFail($id);
        $request->update(['resolved_at' => now()]);

        if ($request->session) {
            Realtime::table($request->session->token, 'service');
        }
    }

    /** The table has paid and left: start a fresh session for the next guests. */
    public function closeTable(int $sessionId): void
    {
        $session = TableSession::findOrFail($sessionId);
        $session->close();

        Realtime::table($session->token, 'closed');
    }

    public function toggleAvailability(int $itemId): void
    {
        $item = MenuItem::findOrFail($itemId);
        $item->update(['is_available' => ! $item->is_available]);

        Realtime::menu();
    }

    public function render()
    {
        $orders = Order::with(['table', 'items'])
            ->whereNotIn('status', ['served', Order::CANCELLED])
            ->oldest()
            ->get();

        if ($this->sort === 'stage') {
            $rank = array_flip(Order::STATUSES);
            $orders = $orders->sortBy(fn (Order $o) => [$rank[$o->status], $o->id])->values();
        }

        // Polling fallback: beep when a brand-new order shows up between refreshes.
        $newest = (int) $orders->max('id');
        if ($this->lastSeenId && $newest > $this->lastSeenId) {
            $this->dispatch('new-order', kind: 'order.placed');
        }
        $this->lastSeenId = max($this->lastSeenId, $newest);

        $requests = ServiceRequest::with(['table', 'session.orders'])
            ->whereNull('resolved_at')
            ->oldest()
            ->get();

        return view('livewire.kitchen', [
            'orders' => $orders,
            // Served in the last hour, so a premature "served" tap can be undone.
            'recentlyServed' => Order::with('table')
                ->where('status', 'served')
                ->where('served_at', '>=', now()->subHour())
                ->latest('served_at')
                ->limit(5)
                ->get(),
            'requests' => $requests,
            'categories' => $this->tab === 'menu' ? Category::with('items')->orderBy('sort_order')->get() : collect(),
        ]);
    }
}
