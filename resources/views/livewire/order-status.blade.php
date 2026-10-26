<div wire:poll.{{ config('trattoria.poll.guest') }}s class="mx-auto max-w-lg px-4 py-8">
    @php
        $steps = [
            'received' => [__('Order received'), __('Your order reached the kitchen.')],
            'preparing' => [__('Preparing'), __('The chefs are cooking your food.')],
            'ready' => [__('Ready'), __('Your food is ready and on its way!')],
            'served' => [__('Served'), __('Buon appetito! Enjoy your meal.')],
        ];
        $cancelled = $order->isCancelled();
        $current = array_search($order->status, array_keys($steps));
        $totalItems = $order->items->count();
        $readyItems = $order->readyItemsCount();

        // Estimated wait, shown while the food is still being prepared.
        $remaining = max(0, $wait - (int) $order->created_at->diffInMinutes(now()));
    @endphp

    @if ($justPlaced && ! $cancelled)
        <div class="rise-in mb-6 text-center">
            <svg viewBox="0 0 56 56" class="mx-auto h-20 w-20" fill="none" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <circle class="draw-circle" cx="28" cy="28" r="26" stroke="#008c45"/>
                <path class="draw-check" d="M16 29l8 8 16-17" stroke="#008c45"/>
            </svg>
            <h2 class="font-display mt-3 text-3xl font-bold text-basil-dark">{{ __('Order sent!') }}</h2>
            <p class="text-ink/70">{{ __('The kitchen has your order and will start cooking.') }}</p>
        </div>
    @endif

    {{-- Lost connection to the server: tell the guest instead of showing stale information silently. --}}
    <p wire:offline class="mb-4 rounded-lg bg-amber-100 px-4 py-2 text-center text-sm font-bold text-amber-900" role="status">{{ __('You are offline. Reconnecting...') }}</p>

    <div class="overflow-hidden rounded-2xl bg-white shadow-lg">
        <div class="tablecloth border-b-4 border-tomato py-6 text-center">
            <h1 class="font-display inline-block rounded-lg bg-cream/95 px-4 py-1 text-3xl font-bold text-tomato">{{ __('Order') }} #{{ $order->id }}</h1>
            <p class="font-display mt-2 italic">{{ __('Table') }} {{ $table->number }}</p>
        </div>

        @if ($cancelled)
            <div class="p-6 text-center">
                <p class="font-display text-2xl font-bold text-tomato">{{ __('Order cancelled') }}</p>
                <p class="mt-1 text-ink/70">{{ __('This order was cancelled by the restaurant. It will not appear on your bill.') }}</p>
            </div>
        @else
            @if (in_array($order->status, ['received', 'preparing'], true))
                <p class="border-b border-ink/10 bg-cream px-6 py-3 text-center text-sm font-bold text-ink/80">
                    @if ($remaining > 0)
                        {{ __('Estimated wait') }}: {{ __('about :minutes min', ['minutes' => $remaining]) }}
                    @else
                        {{ __('Taking a little longer than usual. Thank you for your patience.') }}
                    @endif
                </p>
            @endif

            <ol class="space-y-4 p-6">
                @foreach ($steps as $key => [$label, $text])
                    @php $i = array_search($key, array_keys($steps)); @endphp
                    <li class="flex items-center gap-3 {{ $i > $current ? 'text-ink/70' : '' }}" @if ($i === $current) aria-current="step" @endif>
                        <span class="flex h-9 w-9 items-center justify-center rounded-full font-bold
                            {{ $i < $current ? 'bg-basil text-white' : ($i === $current ? 'animate-pulse bg-tomato text-white' : 'bg-ink/10 text-ink/70') }}">
                            @if ($i < $current)<x-glyph name="check" class="h-5 w-5"/>@else{{ $i + 1 }}@endif
                        </span>
                        <div>
                            <p class="font-bold">{{ $label }}</p>
                            @if ($i === $current)<p class="text-sm text-ink/70">{{ $text }}</p>@endif
                        </div>
                    </li>
                @endforeach
            </ol>
        @endif

        <div class="border-t border-ink/10 p-6">
            @if (! $cancelled && $order->status === 'preparing' && $totalItems > 1)
                <p class="mb-2 text-sm font-bold text-basil-dark">{{ __(':ready of :total dishes ready', ['ready' => $readyItems, 'total' => $totalItems]) }}</p>
            @endif
            <ul class="space-y-1.5 text-sm {{ $cancelled ? 'line-through opacity-60' : '' }}">
                @foreach ($order->items as $item)
                    <li class="flex justify-between gap-3">
                        <span class="flex items-start gap-2">
                            @if (! $cancelled && $order->status === 'preparing' && $item->is_ready)
                                <x-glyph name="check" class="mt-0.5 h-4 w-4 shrink-0 text-basil"/>
                            @endif
                            <span>
                                {{ $item->quantity }} x {{ $item->name }}
                                @if ($item->options_text)<span class="block text-xs text-ink/70">{{ $item->options_text }}</span>@endif
                            </span>
                        </span>
                        <span>&euro;{{ number_format($item->price_cents * $item->quantity / 100, 2, ',', '.') }}</span>
                    </li>
                @endforeach
            </ul>
            @if ($order->notes)<p class="mt-3 rounded bg-cream p-2 text-sm text-ink/70">{{ $order->notes }}</p>@endif
            <p class="mt-3 flex justify-between text-lg font-bold {{ $cancelled ? 'line-through opacity-60' : '' }}"><span>{{ __('Total') }}</span><span class="text-tomato">&euro;{{ $order->total }}</span></p>
        </div>

        <div class="flex justify-between gap-3 bg-cream p-4 text-sm font-bold">
            <a href="{{ route('table.menu', $table->token) }}" class="inline-flex min-h-11 items-center gap-1.5 text-basil-dark underline"><x-glyph name="arrow-left" class="h-4 w-4"/>{{ __('Order something else') }}</a>
            <a href="{{ route('table.bill', $table->token) }}" class="inline-flex min-h-11 items-center text-basil-dark underline">{{ __('Orders and bill') }}</a>
        </div>
    </div>
</div>
