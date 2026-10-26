<div wire:poll.{{ config('trattoria.poll.bill') }}s class="mx-auto max-w-lg px-4 py-8">
    <a href="{{ route('table.menu', $table->token) }}" class="mb-4 inline-flex min-h-11 items-center gap-1.5 text-sm font-bold text-basil-dark underline"><x-glyph name="arrow-left" class="h-4 w-4"/>{{ __('Back to the menu') }}</a>

    <p wire:offline class="mb-4 rounded-lg bg-amber-100 px-4 py-2 text-center text-sm font-bold text-amber-900" role="status">{{ __('You are offline. Reconnecting...') }}</p>

    @php
        $billable = $orders->where('status', '!=', 'cancelled');
    @endphp

    <div class="overflow-hidden rounded-2xl bg-white shadow-lg">
        <div class="tablecloth border-b-4 border-tomato py-6 text-center">
            <h1 class="font-display inline-block rounded-lg bg-cream/95 px-4 py-1 text-3xl font-bold text-tomato">{{ __('Your bill') }}</h1>
            <p class="font-display mt-2 italic">{{ __('Table') }} {{ $table->number }}</p>
        </div>

        @forelse ($orders as $order)
            @php $isCancelled = $order->status === 'cancelled'; @endphp
            <div class="border-b border-ink/10 p-5">
                <div class="flex items-center justify-between text-xs">
                    <a href="{{ route('table.order', [$table->token, $order->id]) }}" class="inline-flex min-h-11 items-center font-bold text-basil-dark underline">{{ __('Order') }} #{{ $order->id }}</a>
                    <span class="rounded-full px-2.5 py-0.5 font-bold uppercase {{ $isCancelled ? 'bg-tomato/10 text-tomato' : 'bg-cream text-ink/70' }}">{{ __(ucfirst($order->status)) }}</span>
                </div>
                <ul class="mt-2 space-y-1.5 text-sm {{ $isCancelled ? 'line-through opacity-60' : '' }}">
                    @foreach ($order->items as $item)
                        <li class="flex justify-between gap-3">
                            <span>{{ $item->quantity }} x {{ $item->name }}@if ($item->options_text)<span class="block text-xs text-ink/70">{{ $item->options_text }}</span>@endif</span>
                            <span>&euro;{{ number_format($item->price_cents * $item->quantity / 100, 2, ',', '.') }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @empty
            <p class="p-8 text-center text-ink/70">{{ __('You have not ordered anything yet.') }}</p>
        @endforelse

        @if ($billable->isNotEmpty())
            <div class="font-display flex justify-between p-5 text-2xl font-bold">
                <span>{{ __('Total') }}</span>
                <span class="text-tomato">&euro;{{ number_format($billable->sum('total_cents') / 100, 2, ',', '.') }}</span>
            </div>
        @endif

        <div class="space-y-2 bg-cream p-4">
            <button wire:click="requestBill" @disabled($billRequested || $billable->isEmpty())
                    class="flex min-h-11 w-full items-center justify-center gap-2 rounded-full bg-basil py-3 font-bold text-white shadow transition hover:bg-basil-dark disabled:cursor-not-allowed disabled:opacity-50">
                <x-glyph name="receipt" class="h-5 w-5"/>
                {{ $billRequested ? __('Bill requested') : __('Request the bill') }}
            </button>
            <button wire:click="callWaiter" @disabled($waiterRequested)
                    class="flex min-h-11 w-full items-center justify-center gap-2 rounded-full border border-tomato/40 bg-white py-3 font-bold text-tomato transition hover:bg-tomato hover:text-white disabled:opacity-50 disabled:hover:bg-white disabled:hover:text-tomato">
                <x-glyph name="bell" class="h-5 w-5"/>
                {{ $waiterAcknowledged ? __('Waiter on the way') : ($waiterRequested ? __('Waiter requested') : __('Call a waiter')) }}
            </button>
            <p class="text-center text-xs text-ink/70">{{ __('Payment is made at the table.') }}</p>
        </div>
    </div>
</div>
