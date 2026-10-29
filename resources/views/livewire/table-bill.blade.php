<div @unless ($payOpen) wire:poll.{{ config('trattoria.poll.bill') }}s @endunless class="mx-auto max-w-lg px-4 py-8">
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

        @if ($paymentsOn && $billable->isNotEmpty())
            <div class="space-y-1 border-t border-ink/10 px-5 py-4 text-sm" aria-live="polite">
                <div class="flex justify-between"><span>{{ __('Paid so far') }}</span><span>&euro;{{ number_format($paidCents / 100, 2, ',', '.') }}</span></div>
                <div class="flex justify-between font-bold {{ $dueCents === 0 ? 'text-basil-dark' : '' }}">
                    <span>{{ $dueCents === 0 ? __('Paid in full. Thank you!') : __('Left to pay') }}</span>
                    <span>&euro;{{ number_format($dueCents / 100, 2, ',', '.') }}</span>
                </div>
            </div>

            @if ($payments->isNotEmpty())
                <div class="border-t border-ink/10 px-5 py-4">
                    <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-ink/70">{{ __('Payments') }}</h2>
                    <ul class="space-y-2 text-sm">
                        @foreach ($payments as $payment)
                            <li class="flex items-start justify-between gap-3 rounded-lg bg-cream px-3 py-2 {{ $lastPaymentId === $payment->id ? 'ring-2 ring-basil' : '' }}">
                                <span>
                                    <span class="font-bold">{{ $payment->label ?? __('Payment') }}</span>
                                    <span class="block text-xs text-ink/70">{{ $payment->card_brand }} &bull;&bull;&bull;&bull; {{ $payment->card_last4 }} &middot; {{ $payment->paid_at->format('H:i') }} &middot; {{ __('receipt') }} {{ $payment->reference }}</span>
                                </span>
                                <span class="text-right font-bold">&euro;{{ number_format($payment->amount_cents / 100, 2, ',', '.') }}@if ($payment->tip_cents > 0)<span class="block text-xs font-normal text-ink/70">+ &euro;{{ number_format($payment->tip_cents / 100, 2, ',', '.') }} {{ __('tip') }}</span>@endif</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if ($dueCents > 0)
                <div class="border-t border-ink/10 p-4">
                    @unless ($payOpen)
                        <button wire:click="openPay" class="flex min-h-11 w-full items-center justify-center gap-2 rounded-full bg-tomato py-3 font-bold text-white shadow transition hover:opacity-90">
                            {{ __('Pay now') }} <span class="rounded-full bg-white/20 px-2 py-0.5 text-xs">{{ __('demo') }}</span>
                        </button>
                    @else
                        <form wire:submit="pay" class="space-y-4" autocomplete="off">
                            <div class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-900" role="note">
                                {{ __('This is a demo. No real money moves. Use the test card 4242 4242 4242 4242, any future date and any 3 digits. Please do not type a real card number.') }}
                            </div>

                            <fieldset>
                                <legend class="mb-1 text-sm font-bold">{{ __('How do you want to pay?') }}</legend>
                                <div class="grid gap-2 sm:grid-cols-3">
                                    @foreach (['whole' => __('Whole bill'), 'split' => __('Split equally'), 'items' => __('Choose dishes')] as $value => $text)
                                        <label class="flex min-h-11 cursor-pointer items-center justify-center rounded-full border px-3 py-2 text-center text-sm font-bold has-[:checked]:border-basil has-[:checked]:bg-basil has-[:checked]:text-white">
                                            <input type="radio" wire:model.live="payMode" value="{{ $value }}" class="sr-only">
                                            {{ $text }}
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>

                            @if ($payMode === 'split')
                                <label class="block text-sm font-bold">{{ __('How many people share what is left?') }}
                                    <input type="number" min="2" max="{{ \App\Support\Payments::MAX_PEOPLE }}" wire:model.live.debounce.300ms="people" inputmode="numeric" class="mt-1 block min-h-11 w-24 rounded-lg border border-ink/30 px-3 py-2">
                                </label>
                                <p class="text-xs text-ink/70">{{ __('Each share is rounded up; the last person only pays what is left.') }}</p>
                            @elseif ($payMode === 'items')
                                <fieldset>
                                    <legend class="mb-1 text-sm font-bold">{{ __('Tick your dishes') }}</legend>
                                    <ul class="space-y-1">
                                        @forelse ($unpaidItems as $item)
                                            <li>
                                                <label class="flex min-h-11 cursor-pointer items-center justify-between gap-3 rounded-lg bg-cream px-3 py-2 text-sm">
                                                    <span class="flex items-center gap-2">
                                                        <input type="checkbox" wire:model.live="pickedItems" value="{{ $item->id }}" class="h-5 w-5 accent-[#2f6b3a]">
                                                        {{ $item->quantity }} x {{ $item->name }}
                                                    </span>
                                                    <span>&euro;{{ number_format($item->price_cents * $item->quantity / 100, 2, ',', '.') }}</span>
                                                </label>
                                            </li>
                                        @empty
                                            <li class="text-sm text-ink/70">{{ __('Every dish is paid for.') }}</li>
                                        @endforelse
                                    </ul>
                                </fieldset>
                            @endif

                            <fieldset>
                                <legend class="mb-1 text-sm font-bold">{{ __('Add a tip?') }}</legend>
                                <div class="flex flex-wrap gap-2">
                                    @foreach ([0, 5, 10, 15] as $percent)
                                        <label class="flex min-h-11 min-w-14 cursor-pointer items-center justify-center rounded-full border px-3 py-2 text-sm font-bold has-[:checked]:border-basil has-[:checked]:bg-basil has-[:checked]:text-white">
                                            <input type="radio" wire:model.live="tipPercent" value="{{ $percent }}" class="sr-only">
                                            {{ $percent === 0 ? __('No tip') : $percent . '%' }}
                                        </label>
                                    @endforeach
                                </div>
                            </fieldset>

                            <div class="space-y-2">
                                <label class="block text-sm font-bold">{{ __('Your name (optional)') }}
                                    <input type="text" wire:model="payerName" maxlength="30" autocomplete="off" class="mt-1 block min-h-11 w-full rounded-lg border border-ink/30 px-3 py-2">
                                </label>
                                <label class="block text-sm font-bold">{{ __('Card number') }}
                                    <input type="text" wire:model="cardNumber" inputmode="numeric" autocomplete="off" placeholder="4242 4242 4242 4242" maxlength="23" class="mt-1 block min-h-11 w-full rounded-lg border border-ink/30 px-3 py-2">
                                </label>
                                <div class="grid grid-cols-2 gap-2">
                                    <label class="block text-sm font-bold">{{ __('Expiry') }}
                                        <input type="text" wire:model="cardExpiry" inputmode="numeric" autocomplete="off" placeholder="08/29" maxlength="7" class="mt-1 block min-h-11 w-full rounded-lg border border-ink/30 px-3 py-2">
                                    </label>
                                    <label class="block text-sm font-bold">{{ __('Security code') }}
                                        <input type="text" wire:model="cardCvc" inputmode="numeric" autocomplete="off" placeholder="123" maxlength="4" class="mt-1 block min-h-11 w-full rounded-lg border border-ink/30 px-3 py-2">
                                    </label>
                                </div>
                            </div>

                            @if ($payError)
                                <p role="alert" class="rounded-lg bg-tomato/10 px-3 py-2 text-sm font-bold text-tomato">{{ $payError }}</p>
                            @endif

                            <div class="flex flex-col gap-2 sm:flex-row">
                                <button type="submit" @disabled($payAmountCents <= 0) class="flex min-h-11 flex-1 items-center justify-center rounded-full bg-tomato py-3 font-bold text-white shadow transition hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-50">
                                    {{ __('Pay') }} &euro;{{ number_format(($payAmountCents + $payTipCents) / 100, 2, ',', '.') }}@if ($payTipCents > 0) <span class="ml-1 text-xs font-normal">({{ __('includes :tip tip', ['tip' => '€' . number_format($payTipCents / 100, 2, ',', '.')]) }})</span>@endif
                                </button>
                                <button type="button" wire:click="closePay" class="min-h-11 rounded-full border border-ink/30 px-5 py-3 font-bold">{{ __('Cancel') }}</button>
                            </div>
                        </form>
                    @endunless
                </div>
            @endif
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
            <p class="text-center text-xs text-ink/70">{{ $paymentsOn ? __('You can pay here, or at the table.') : __('Payment is made at the table.') }}</p>
        </div>
    </div>
</div>
