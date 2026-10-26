<?php

namespace App\Livewire;

use App\Models\DiningTable;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Support\Ordering;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TableBill extends Component
{
    public DiningTable $table;

    public TableSession $session;

    public string $channelToken = '';

    public function mount(string $token): void
    {
        $this->table = DiningTable::where('token', $token)->firstOrFail();
        $this->session = TableSession::forTable($this->table);
        $this->channelToken = $this->session->token;
    }

    public function getListeners(): array
    {
        return config('trattoria.realtime') === 'reverb' ? ["echo:table.{$this->channelToken},.updated" => '$refresh'] : [];
    }

    public function requestBill(): void
    {
        Ordering::requestService($this->session, ServiceRequest::BILL);
        $this->dispatch('toast', message: __('The bill is on its way.'));
    }

    public function callWaiter(): void
    {
        Ordering::requestService($this->session, ServiceRequest::WAITER);
        $this->dispatch('toast', message: __('A waiter is on the way.'));
    }

    public function render()
    {
        if (TableSession::forTable($this->table)->id !== $this->session->id) {
            $this->redirectRoute('table.bill', $this->table->token);
        }

        $open = $this->session->serviceRequests()->whereNull('resolved_at')->get();
        $requests = $open->pluck('type');

        return view('livewire.table-bill', [
            'orders' => $this->session->orders()->with('items')->oldest()->get(),
            'billRequested' => $requests->contains(ServiceRequest::BILL),
            'waiterRequested' => $requests->contains(ServiceRequest::WAITER),
            'waiterAcknowledged' => $open->where('type', ServiceRequest::WAITER)->whereNotNull('acknowledged_at')->isNotEmpty(),
        ]);
    }
}
