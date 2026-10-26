<div>
    @if ($open)
        <div class="fixed inset-0 z-30 flex items-end justify-center bg-black/55 sm:items-center" wire:click.self="close"
             x-data="{ open: true }" x-on:keydown.escape.window="$wire.close()">
            <div class="rise-in max-h-[92dvh] w-full max-w-lg overflow-y-auto rounded-t-3xl bg-cream sm:rounded-3xl"
                 role="dialog" aria-modal="true" aria-label="{{ __('Your order') }}" x-trap.noscroll="open">
                <div class="tricolore rounded-t-3xl"></div>
                <div class="p-5">
                    <div class="mb-1 flex items-center justify-between">
                        <h2 class="font-display text-2xl font-bold text-tomato">{{ __('Your order') }}</h2>
                        <button type="button" wire:click="close" class="flex h-11 w-11 items-center justify-center rounded-full hover:bg-ink/5" aria-label="{{ __('Close') }}"><x-glyph name="x" class="h-5 w-5"/></button>
                    </div>
                    <p class="mb-3 flex items-center gap-1.5 text-xs text-ink/70"><x-glyph name="users" class="h-4 w-4"/>{{ __('Shared with everyone at your table') }}</p>

                    @if ($this->lines->isEmpty())
                        <p class="py-10 text-center text-ink/70">{{ __('Your order is empty.') }}</p>
                        <button type="button" wire:click="close" class="w-full rounded-full border border-basil py-3 font-bold text-basil-dark">{{ __('Back to the menu') }}</button>
                    @else
                        <ul class="divide-y divide-ink/10">
                            @foreach ($this->lines as $line)
                                <li class="flex items-center gap-3 py-3" wire:key="line-{{ $line->id }}">
                                    <x-dish-photo :item="$line->menuItem" class="h-12 w-12 shrink-0 rounded-lg"/>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-bold leading-tight">{{ $line->menuItem->name }}</p>
                                        @if ($line->optionsText())<p class="text-xs text-ink/70">{{ $line->optionsText() }}</p>@endif
                                        <p class="text-sm text-ink/70">&euro;{{ number_format($line->unitPriceCents() / 100, 2, ',', '.') }} {{ __('each') }}</p>
                                    </div>
                                    <div class="inline-flex items-center rounded-full bg-basil text-white">
                                        <button type="button" wire:click="changeLine({{ $line->id }}, -1)" class="flex h-11 w-11 items-center justify-center" aria-label="{{ __('Remove one') }}: {{ $line->menuItem->name }}"><x-glyph name="minus" class="h-4 w-4"/></button>
                                        <span class="w-6 text-center font-bold" aria-live="polite">{{ $line->quantity }}</span>
                                        <button type="button" wire:click="changeLine({{ $line->id }}, 1)" class="flex h-11 w-11 items-center justify-center" aria-label="{{ __('Add one') }}: {{ $line->menuItem->name }}"><x-glyph name="plus" class="h-4 w-4"/></button>
                                    </div>
                                    <span class="w-16 text-right font-bold">&euro;{{ number_format($line->lineTotalCents() / 100, 2, ',', '.') }}</span>
                                </li>
                            @endforeach
                        </ul>

                        <label for="order-notes" class="mt-4 block text-sm font-bold">{{ __('Notes for the kitchen (optional)') }}</label>
                        <textarea id="order-notes" wire:model="notes" rows="2" maxlength="200" placeholder="{{ __('e.g. no onions, nut allergy...') }}"
                                  class="mt-1 w-full rounded-lg border border-ink/20 bg-white p-2 text-base sm:text-sm"></textarea>
                        @error('notes') <p class="text-sm text-tomato">{{ $message }}</p> @enderror

                        <div class="font-display mt-4 flex justify-between text-xl font-bold">
                            <span>{{ __('Total') }}</span>
                            <span class="text-tomato">&euro;{{ number_format($this->totalCents / 100, 2, ',', '.') }}</span>
                        </div>

                        <button type="button" wire:click="placeOrder" wire:loading.attr="disabled" wire:target="placeOrder"
                                class="mt-4 w-full rounded-full bg-basil py-3.5 text-lg font-bold text-white shadow transition hover:bg-basil-dark disabled:opacity-60">
                            <span wire:loading.remove wire:target="placeOrder">{{ __('Send order to the kitchen') }}</span>
                            <span wire:loading wire:target="placeOrder">{{ __('Sending...') }}</span>
                        </button>
                        <p class="mt-2 text-center text-xs text-ink/70">{{ __("You'll pay at the table. You can track your order after sending it.") }}</p>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
