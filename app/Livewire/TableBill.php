<?php

namespace App\Livewire;

use App\Models\DiningTable;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Support\Ordering;
use App\Support\PaymentException;
use App\Support\Payments;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class TableBill extends Component
{
    public DiningTable $table;

    public TableSession $session;

    public string $channelToken = '';

    // Paying from the phone (a demo gateway that only takes test cards)
    public bool $payOpen = false;

    /** 'whole' = everything that is left, 'split' = an equal share, 'items' = only the dishes ticked */
    public string $payMode = 'whole';

    public int $people = 2;

    /** @var int[] */
    public array $pickedItems = [];

    public int $tipPercent = 0;

    public string $payerName = '';

    public string $cardNumber = '';

    public string $cardExpiry = '';

    public string $cardCvc = '';

    public ?string $payError = null;

    public ?int $lastPaymentId = null;

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

    public function openPay(): void
    {
        if (Payments::enabled()) {
            $this->payOpen = true;
            $this->payError = null;
        }
    }

    public function closePay(): void
    {
        $this->payOpen = false;
        $this->payError = null;
        $this->clearCard();
    }

    public function updatedPayMode(): void
    {
        $this->payError = null;
    }

    public function updatedPeople(): void
    {
        $this->people = max(2, min(Payments::MAX_PEOPLE, (int) $this->people));
    }

    /** What the person is about to pay for the bill itself (without the tip), given the way they chose. */
    private function amountToPay(int $due): int
    {
        return match ($this->payMode) {
            'split' => min($due, Payments::shareCents($due, $this->people)),
            'items' => (int) Payments::unpaidItems($this->session)->whereIn('id', array_map('intval', $this->pickedItems))->sum(fn ($item) => $item->price_cents * $item->quantity),
            default => $due,
        };
    }

    public function pay(): void
    {
        $this->payError = null;

        try {
            $due = Payments::dueCents($this->session);
            $amount = $this->amountToPay($due);
            $label = match ($this->payMode) {
                'split' => __('Share of :people', ['people' => $this->people]),
                'items' => __('Own dishes'),
                default => __('Whole bill'),
            };
            if (trim($this->payerName) !== '') {
                $label = trim($this->payerName).': '.$label;
            }

            $payment = Payments::pay(
                $this->session,
                match ($this->payMode) {
                    'split' => 'share',
                    'items' => 'items',
                    default => 'whole',
                },
                $amount,
                Payments::tipCents($amount, $this->tipPercent),
                ['number' => $this->cardNumber, 'expiry' => $this->cardExpiry, 'cvc' => $this->cardCvc],
                $label,
                $this->pickedItems,
            );
        } catch (PaymentException $e) {
            $this->payError = $e->getMessage();

            return;
        } finally {
            // the card details are never kept, not even on the page, whatever happened
            $this->clearCard();
        }

        $this->lastPaymentId = $payment->id;
        $this->pickedItems = [];
        $this->tipPercent = 0;
        $this->payOpen = false; // the receipt shows; tap "Pay" again for the next share
        $this->dispatch('toast', message: __('Payment received. Thank you!'));
    }

    private function clearCard(): void
    {
        $this->cardNumber = '';
        $this->cardExpiry = '';
        $this->cardCvc = '';
    }

    public function render()
    {
        if (TableSession::forTable($this->table)->id !== $this->session->id) {
            $this->redirectRoute('table.bill', $this->table->token);
        }

        $open = $this->session->serviceRequests()->whereNull('resolved_at')->get();
        $requests = $open->pluck('type');

        $payments = Payments::enabled();
        $due = $payments ? Payments::dueCents($this->session) : 0;
        $amount = $payments && $this->payOpen ? $this->amountToPay($due) : 0;

        return view('livewire.table-bill', [
            'orders' => $this->session->orders()->with('items')->oldest()->get(),
            'billRequested' => $requests->contains(ServiceRequest::BILL),
            'waiterRequested' => $requests->contains(ServiceRequest::WAITER),
            'waiterAcknowledged' => $open->where('type', ServiceRequest::WAITER)->whereNotNull('acknowledged_at')->isNotEmpty(),
            'paymentsOn' => $payments,
            'dueCents' => $due,
            'paidCents' => $payments ? Payments::paidCents($this->session) : 0,
            'payments' => $payments ? $this->session->payments()->latest('paid_at')->latest('id')->get() : collect(),
            'unpaidItems' => $payments && $this->payMode === 'items' && $this->payOpen ? Payments::unpaidItems($this->session) : collect(),
            'payAmountCents' => $amount,
            'payTipCents' => Payments::tipCents($amount, $this->tipPercent),
        ]);
    }
}
