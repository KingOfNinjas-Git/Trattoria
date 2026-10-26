<?php

namespace App\Livewire;

use App\Models\DiningTable;
use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('layouts.app')]
class OrderStatus extends Component
{
    public DiningTable $table;

    public Order $order;

    /** Set right after the order was sent, to show the confirmation animation once. */
    #[Url(as: 'placed')]
    public bool $justPlaced = false;

    public function mount(string $token, Order $order): void
    {
        $this->table = DiningTable::where('token', $token)->firstOrFail();
        abort_unless($order->dining_table_id === $this->table->id, 404);
        $this->order = $order;
    }

    public function getListeners(): array
    {
        $token = $this->order->session?->token;

        return $token && config('trattoria.realtime') === 'reverb' ? ["echo:table.{$token},.updated" => '$refresh'] : [];
    }

    public function render()
    {
        $this->order->refresh()->load('items');

        return view('livewire.order-status', ['wait' => Order::estimatedWaitMinutes()]);
    }
}
