<div>
    @if ($this->selected)
        @php
            $s = $this->selected;
            $allergenLabels = collect(\App\Models\MenuItem::ALLERGENS);
            $missingRequired = collect($s->options ?? [])->contains(fn ($g, $gi) => ! empty($g['required']) && empty($choices[$gi] ?? []));
        @endphp
        <div class="fixed inset-0 z-30 flex items-end justify-center bg-black/55 sm:items-center" wire:click.self="close"
             x-data="{ open: true }" x-on:keydown.escape.window="$wire.close()">
            <div class="rise-in flex max-h-[92dvh] w-full max-w-lg flex-col overflow-hidden rounded-t-3xl bg-cream sm:rounded-3xl"
                 role="dialog" aria-modal="true" aria-label="{{ $s->name }}" x-trap.noscroll="open">
                <div class="relative shrink-0">
                    <x-dish-photo :item="$s" :eager="true" class="aspect-[4/3] max-h-[38dvh] w-full"/>
                    <button type="button" wire:click="close" class="absolute right-3 top-3 flex h-11 w-11 items-center justify-center rounded-full bg-white/90 shadow" aria-label="{{ __('Close') }}"><x-glyph name="x" class="h-5 w-5"/></button>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto p-5">
                    <div class="flex items-start justify-between gap-3">
                        <h2 class="font-display text-2xl font-bold leading-tight">{{ $s->name }}</h2>
                        <span class="shrink-0 text-lg font-bold text-tomato">&euro;{{ $s->price }}</span>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1.5">
                        @if ($s->is_vegetarian)<span class="rounded-full bg-basil/10 px-2.5 py-0.5 text-[11px] font-bold uppercase text-basil-dark">{{ __('Vegetarian') }}</span>@endif
                        @if ($s->is_spicy)<span class="inline-flex items-center gap-1 rounded-full bg-tomato/10 px-2.5 py-0.5 text-[11px] font-bold uppercase text-tomato"><x-glyph name="flame" class="h-3 w-3"/>{{ __('Spicy') }}</span>@endif
                    </div>
                    <p class="mt-3 text-ink/80">{{ $s->localized('description') }}</p>
                    @if ($s->allergens)
                        <p class="mt-2 text-sm text-ink/70"><span class="font-bold">{{ __('Contains') }}:</span> {{ collect($s->allergens)->map(fn ($a) => __($allergenLabels[$a] ?? $a))->implode(', ') }}</p>
                    @endif

                    @foreach ($s->options ?? [] as $gi => $group)
                        <fieldset class="mt-5">
                            <legend class="flex w-full items-baseline justify-between text-sm font-bold">
                                <span>{{ __($group['name']) }}</span>
                                <span class="text-xs font-normal text-ink/70">{{ ! empty($group['required']) ? __('Required') : __('Optional') }}@if (($group['type'] ?? 'single') === 'multiple') &middot; {{ __('choose any') }}@endif</span>
                            </legend>
                            <div class="mt-2 space-y-2">
                                @foreach ($group['choices'] as $ci => $choice)
                                    @php $on = in_array($ci, $choices[$gi] ?? [], true); @endphp
                                    <button type="button" wire:click="toggleChoice({{ $gi }}, {{ $ci }})" aria-pressed="{{ $on ? 'true' : 'false' }}"
                                            class="flex min-h-11 w-full items-center justify-between rounded-xl border px-4 py-2.5 text-left text-sm transition {{ $on ? 'border-basil bg-basil/10 font-bold' : 'border-ink/15 bg-white hover:border-basil' }}">
                                        <span class="flex items-center gap-3">
                                            <span class="flex h-5 w-5 items-center justify-center border-2 {{ ($group['type'] ?? 'single') === 'single' ? 'rounded-full' : 'rounded' }} {{ $on ? 'border-basil bg-basil text-white' : 'border-ink/30' }}">
                                                @if ($on)<x-glyph name="check" class="h-3 w-3" />@endif
                                            </span>
                                            {{ __($choice['name']) }}
                                        </span>
                                        @if (($choice['price_cents'] ?? 0) > 0)
                                            <span class="text-ink/70">+&euro;{{ number_format($choice['price_cents'] / 100, 2, ',', '.') }}</span>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </fieldset>
                    @endforeach
                </div>

                <div class="flex shrink-0 items-center gap-3 border-t border-ink/10 bg-white p-4 pb-[max(1rem,env(safe-area-inset-bottom))]">
                    <div class="inline-flex items-center rounded-full border border-ink/15">
                        <button type="button" wire:click="stepQty(-1)" class="flex h-11 w-11 items-center justify-center" aria-label="{{ __('Remove one') }}"><x-glyph name="minus" class="h-4 w-4"/></button>
                        <span class="w-6 text-center font-bold" aria-live="polite">{{ $qty }}</span>
                        <button type="button" wire:click="stepQty(1)" class="flex h-11 w-11 items-center justify-center" aria-label="{{ __('Add one') }}"><x-glyph name="plus" class="h-4 w-4"/></button>
                    </div>
                    @if ($s->isOrderable())
                        <button type="button" wire:click="add" @disabled($missingRequired)
                                class="flex flex-1 items-center justify-between rounded-full bg-tomato px-5 py-3 font-bold text-white shadow transition hover:bg-tomato-dark disabled:cursor-not-allowed disabled:opacity-50">
                            <span>{{ __('Add to order') }}</span>
                            <span>&euro;{{ number_format($this->priceCents / 100, 2, ',', '.') }}</span>
                        </button>
                    @else
                        <span class="flex-1 text-center font-bold uppercase text-tomato">{{ $s->is_available ? __('Not served right now') : __('Sold out') }}</span>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
