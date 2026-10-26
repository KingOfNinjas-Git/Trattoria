@php
    $allergenLabels = collect(\App\Models\MenuItem::ALLERGENS);
    $index = $this->categories->map(fn ($c) => [
        'id' => $c->id,
        'items' => $c->items->map(fn ($i) => [
            'id' => $i->id,
            'text' => mb_strtolower($i->name.' '.$i->localized('description')),
            'veg' => (bool) $i->is_vegetarian,
            'spicy' => (bool) $i->is_spicy,
            'allergens' => $i->allergens ?? [],
        ])->values(),
    ])->values();
    // Base prices of dishes that can be added with one tap, for the optimistic cart total.
    $prices = $this->categories->flatMap->items
        ->filter(fn ($i) => $i->is_available && ! $i->hasOptions())
        ->mapWithKeys(fn ($i) => [$i->id => $i->price_cents]);
    $cartVisible = $cartCount > 0;
@endphp

@push('scripts')
<script>
    // Client-side search, filters, category tracking and the instant (optimistic) cart for the menu.
    document.addEventListener('alpine:init', () => {
        Alpine.data('menuBrowser', (index, cfg) => ({
            index,
            token: cfg.token,
            prices: cfg.prices,
            q: '',
            veg: false,
            spicy: false,
            noGluten: false,
            noDairy: false,
            active: null,
            // Taps not yet confirmed by the server: itemId => quantity change.
            pending: {},
            // Taps waiting to be sent as one batch, and the timers that send them.
            queue: {},
            timers: {},

            init() {
                this.active = this.index[0]?.id ?? null;
                const sections = document.querySelectorAll('[data-cat]');
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((e) => { if (e.isIntersecting) this.active = Number(e.target.dataset.cat); });
                }, { rootMargin: '-25% 0px -65% 0px' });
                sections.forEach((s) => observer.observe(s));
                this.$watch('active', (id) => {
                    document.querySelector('[data-chip="' + id + '"]')?.scrollIntoView({ inline: 'center', block: 'nearest' });
                });
                this.listen();
            },

            // Live updates from other phones, the kitchen and menu changes. Our own events are skipped.
            listen(tries = 0) {
                if (!window.Echo) {
                    if (tries < 40) setTimeout(() => this.listen(tries + 1), 100);
                    return;
                }
                window.Echo.channel('table.' + this.token).listen('.updated', (e) => {
                    if (e.origin && e.origin === this.$wire.clientId) return;
                    if (e.kind === 'cart' || e.kind === 'order.placed') this.$wire.syncFromOthers();
                    else if (e.kind !== 'order.status') this.$wire.$refresh();
                });
                window.Echo.channel('menu').listen('.updated', () => this.$wire.$refresh());
            },

            // Quantity shown for a dish: what the server confirmed plus our unconfirmed taps.
            shown(id) { return Math.max(0, (this.$wire.counts[id] || 0) + (this.pending[id] || 0)); },
            get barCount() {
                return Math.max(0, this.$wire.cartCount + Object.values(this.pending).reduce((a, b) => a + b, 0));
            },
            get barCents() {
                const extra = Object.entries(this.pending).reduce((s, [id, n]) => s + n * (this.prices[id] || 0), 0);
                return Math.max(0, this.$wire.cartTotalCents + extra);
            },
            euro(cents) { return '€' + (cents / 100).toFixed(2).replace('.', ','); },
            pop(el) { el.classList.remove('bump'); void el.offsetWidth; el.classList.add('bump'); },

            add(id) { this.bump(id, 1); },
            remove(id) { if (this.shown(id) > 0) this.bump(id, -1); },

            // Show the change immediately, then send everything tapped within 120 ms as one net change.
            bump(id, delta) {
                this.pending[id] = (this.pending[id] || 0) + delta;
                this.queue[id] = (this.queue[id] || 0) + delta;
                clearTimeout(this.timers[id]);
                this.timers[id] = setTimeout(() => this.flush(id), 120);
            },
            flush(id) {
                const delta = this.queue[id] || 0;
                this.queue[id] = 0;
                if (delta === 0) return; // taps cancelled each other out
                this.$wire.quickAdjust(id, delta).finally(() => { this.pending[id] -= delta; });
            },

            get filtering() { return this.q !== '' || this.veg || this.spicy || this.noGluten || this.noDairy; },
            visible(id) {
                for (const c of this.index) {
                    const it = c.items.find((i) => i.id === id);
                    if (it) return this.match(it);
                }
                return true;
            },
            catVisible(catId) {
                return this.index.find((c) => c.id === catId).items.some((i) => this.match(i));
            },
            get anyVisible() { return this.index.some((c) => c.items.some((i) => this.match(i))); },
            match(i) {
                if (this.q && !i.text.includes(this.q.toLowerCase().trim())) return false;
                if (this.veg && !i.veg) return false;
                if (this.spicy && !i.spicy) return false;
                if (this.noGluten && i.allergens.includes('gluten')) return false;
                if (this.noDairy && i.allergens.includes('dairy')) return false;
                return true;
            },
            reset() { this.q = ''; this.veg = this.spicy = this.noGluten = this.noDairy = false; },
        }));
    });
</script>
@endpush

<div class="pb-36" x-data="menuBrowser(@js($index), @js(['token' => $channelToken, 'prices' => $prices]))">
    <p wire:offline class="fixed inset-x-0 top-0 z-40 bg-amber-100 px-4 py-2 text-center text-sm font-bold text-amber-900" role="status">{{ __('You are offline. Reconnecting...') }}</p>

    {{-- Hero --}}
    <header class="relative overflow-hidden bg-tomato text-white">
        <div class="tablecloth absolute inset-0 opacity-20"></div>

        <div class="relative mx-auto max-w-2xl px-4 pt-3">
            <div class="flex justify-end">
                <div class="inline-flex rounded-full bg-black/20 p-0.5 text-xs font-bold" role="group" aria-label="{{ __('Language') }}">
                    @foreach (\App\Http\Middleware\SetLocale::LOCALES as $code => $label)
                        <a href="{{ route('lang', $code) }}" title="{{ $label }}" lang="{{ $code }}"
                           class="inline-flex min-h-10 min-w-11 items-center justify-center rounded-full px-3 transition {{ app()->getLocale() === $code ? 'bg-cream text-tomato' : 'text-white/90 hover:bg-white/15' }}">{{ strtoupper($code) }}</a>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="relative mx-auto max-w-2xl px-4 pb-8 pt-4 text-center">
            <p class="text-xs font-bold uppercase tracking-[.3em] text-white/80">Ristorante &middot; Pizzeria</p>
            <h1 class="font-display mt-2 text-5xl font-bold drop-shadow">Trattoria Bella Vita</h1>
            <p class="font-display mt-2 text-lg italic text-white/90">{{ __('Authentic Italian cooking, made with love') }}</p>

            <div class="mt-5 inline-flex items-center gap-2 rounded-full bg-cream px-5 py-2 font-bold text-tomato shadow">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-basil"></span>
                {{ __('Table') }} {{ $table->number }}
            </div>

            <ol class="mt-6 grid grid-cols-3 gap-2 text-xs text-white/90 sm:text-sm">
                <li class="rounded-lg bg-white/15 px-1 py-3"><x-glyph name="phone" class="mx-auto mb-1 h-6 w-6"/>{{ __('Browse the menu') }}</li>
                <li class="rounded-lg bg-white/15 px-1 py-3"><x-glyph name="bag" class="mx-auto mb-1 h-6 w-6"/>{{ __('Add your dishes') }}</li>
                <li class="rounded-lg bg-white/15 px-1 py-3"><x-glyph name="chef" class="mx-auto mb-1 h-6 w-6"/>{{ __('We start cooking') }}</li>
            </ol>
        </div>
        <div class="tricolore"></div>
    </header>

    {{-- Table actions --}}
    <div class="mx-auto flex max-w-2xl gap-2 px-4 pt-4">
        <button wire:click="callWaiter" @disabled($this->waiterRequested)
                class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-tomato/30 bg-white px-3 py-2.5 text-sm font-bold text-tomato shadow-sm transition hover:bg-tomato hover:text-white disabled:opacity-60 disabled:hover:bg-white disabled:hover:text-tomato">
            <x-glyph name="bell" class="h-4 w-4"/>
            {{ $this->waiterAcknowledged ? __('Waiter on the way') : ($this->waiterRequested ? __('Waiter requested') : __('Call a waiter')) }}
        </button>
        <a href="{{ route('table.bill', $table->token) }}"
           class="flex flex-1 items-center justify-center gap-2 rounded-xl border border-basil/30 bg-white px-3 py-2.5 text-sm font-bold text-basil-dark shadow-sm transition hover:bg-basil hover:text-white">
            <x-glyph name="receipt" class="h-4 w-4"/>
            {{ $this->hasOrders ? __('My orders and bill') : __('Orders and bill') }}
        </a>
    </div>

    {{-- Search and filters --}}
    <div class="mx-auto max-w-2xl px-4 pt-4">
        <label class="relative block">
            <span class="sr-only">{{ __('Search the menu') }}</span>
            <x-glyph name="search" class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-ink/40"/>
            <input type="search" x-model="q" placeholder="{{ __('Search the menu') }}"
                   class="w-full rounded-full border border-ink/15 bg-white py-2.5 pl-10 pr-4 text-base shadow-sm focus:border-basil focus:outline-none focus:ring-2 focus:ring-basil/30 sm:text-sm">
        </label>
        <div class="mt-3 flex flex-wrap gap-2 text-xs font-bold">
            @foreach ([['veg', __('Vegetarian')], ['spicy', __('Spicy')], ['noGluten', __('Gluten-free')], ['noDairy', __('Dairy-free')]] as [$flag, $label])
                <button type="button" x-on:click="{{ $flag }} = !{{ $flag }}" x-bind:aria-pressed="{{ $flag }}"
                        x-bind:class="{{ $flag }} ? 'bg-basil text-white border-basil' : 'bg-white text-ink/70 border-ink/15 hover:border-basil'"
                        class="min-h-10 rounded-full border px-3.5 py-2 transition">{{ $label }}</button>
            @endforeach
            <button type="button" x-show="filtering" x-cloak x-on:click="reset()" class="min-h-10 px-2 py-1.5 text-tomato underline">{{ __('Clear') }}</button>
        </div>
    </div>

    {{-- Category navigation --}}
    <nav class="sticky top-0 z-10 mt-4 overflow-x-auto border-b border-tomato/20 bg-cream/95 shadow-sm backdrop-blur" aria-label="{{ __('Menu categories') }}">
        <ul class="mx-auto flex max-w-2xl gap-2 whitespace-nowrap px-4 py-3">
            @foreach ($this->categories as $category)
                <li x-show="catVisible({{ $category->id }})">
                    <a href="#cat-{{ $category->id }}" data-chip="{{ $category->id }}"
                       x-bind:class="active === {{ $category->id }} && !filtering ? 'bg-basil text-white' : 'text-basil-dark hover:bg-basil hover:text-white'"
                       class="inline-block rounded-full border border-basil px-4 py-1.5 text-sm font-bold transition">
                        {{ $category->localized('name') }}
                    </a>
                </li>
            @endforeach
        </ul>
    </nav>

    <div class="mx-auto max-w-2xl px-4">
        <p x-show="!anyVisible" x-cloak class="py-16 text-center text-ink/70">
            {{ __('No dishes match your search.') }}
            <button type="button" x-on:click="reset()" class="font-bold text-tomato underline">{{ __('Clear filters') }}</button>
        </p>

        @foreach ($this->categories as $category)
            @php $open = $category->isOpenNow(); @endphp
            <section id="cat-{{ $category->id }}" data-cat="{{ $category->id }}" x-show="catVisible({{ $category->id }})" class="scroll-mt-16 pt-10">
                <div class="flex items-end justify-between gap-3">
                    <h2 class="font-display text-3xl font-bold text-tomato">{{ $category->localized('name') }}</h2>
                    @if ($category->localized('subtitle'))
                        <p class="font-display italic text-basil-dark">{{ $category->localized('subtitle') }}</p>
                    @endif
                </div>
                <div class="mb-5 mt-2 h-1 w-full rounded bg-gradient-to-r from-basil via-white to-tomato"></div>
                @if (! $open)
                    <p class="mb-4 inline-flex items-center gap-1.5 rounded-full bg-ink/5 px-3 py-1 text-xs font-bold text-ink/75"><x-glyph name="clock" class="h-3.5 w-3.5"/>{{ __('Served :time', ['time' => $category->scheduleLabel()]) }}</p>
                @endif

                <ul class="space-y-4">
                    @foreach ($category->items as $item)
                        @php $count = $counts[$item->id] ?? 0; $orderable = $item->is_available && $open; @endphp
                        <li wire:key="item-{{ $item->id }}" x-show="visible({{ $item->id }})"
                            class="flex gap-3 rounded-2xl bg-white p-3 min-[380px]:gap-4 shadow-sm ring-1 ring-ink/5 {{ $orderable ? '' : 'opacity-60' }}">
                            {{-- Photo --}}
                            <button type="button" x-on:click="$dispatch('open-dish', { id: {{ $item->id }} })" class="relative h-24 w-24 shrink-0 overflow-hidden rounded-xl min-[380px]:h-28 min-[380px]:w-28 sm:h-32 sm:w-32" aria-label="{{ __('View details') }}: {{ $item->name }}">
                                <x-dish-photo :item="$item" class="h-full w-full"/>
                                @if (! $orderable)
                                    <span class="absolute inset-0 flex items-center justify-center bg-black/55 px-1 text-center text-xs font-bold uppercase text-white">{{ $item->is_available ? __('Not served right now') : __('Sold out') }}</span>
                                @endif
                            </button>

                            {{-- Details --}}
                            <div class="flex min-w-0 flex-1 flex-col">
                                <div class="flex items-start justify-between gap-2">
                                    <button type="button" x-on:click="$dispatch('open-dish', { id: {{ $item->id }} })" class="min-h-8 min-w-0 text-left">
                                        <h3 class="font-display break-words text-lg font-bold leading-tight">{{ $item->name }}</h3>
                                    </button>
                                    <span class="shrink-0 font-bold text-tomato">&euro;{{ $item->price }}</span>
                                </div>

                                <div class="mt-1 flex flex-wrap gap-1">
                                    @if ($item->is_vegetarian)
                                        <span class="rounded-full bg-basil/10 px-2 py-0.5 text-[10px] font-bold uppercase text-basil-dark">{{ __('Vegetarian') }}</span>
                                    @endif
                                    @if ($item->is_spicy)
                                        <span class="inline-flex items-center gap-0.5 rounded-full bg-tomato/10 px-2 py-0.5 text-[10px] font-bold uppercase text-tomato"><x-glyph name="flame" class="h-3 w-3"/>{{ __('Spicy') }}</span>
                                    @endif
                                </div>

                                <p class="mt-1 line-clamp-3 text-sm text-ink/75">{{ $item->localized('description') }}</p>

                                <div class="mt-auto flex items-end justify-between gap-2 pt-2">
                                    @if ($item->allergens)
                                        <p class="text-xs leading-tight text-ink/70">{{ __('Contains') }}: {{ collect($item->allergens)->map(fn ($a) => __($allergenLabels[$a] ?? $a))->implode(', ') }}</p>
                                    @else
                                        <span></span>
                                    @endif

                                    <div class="shrink-0">
                                        @if (! $orderable)
                                            <span class="text-xs font-bold uppercase text-tomato">{{ __('Unavailable') }}</span>
                                        @elseif ($item->hasOptions())
                                            <button type="button" x-on:click="$dispatch('open-dish', { id: {{ $item->id }} })"
                                                    class="min-h-11 rounded-full bg-tomato px-5 py-2 text-sm font-bold text-white shadow transition hover:bg-tomato-dark active:scale-95">
                                                <span x-text="shown({{ $item->id }}) ? shown({{ $item->id }}) + ' ' + @js(__('in order')) : @js(__('Choose'))">{{ $count ? $count.' '.__('in order') : __('Choose') }}</span>
                                            </button>
                                        @else
                                            {{-- Instant: updates in the browser first, the server confirms in the background. --}}
                                            <div x-show="shown({{ $item->id }}) > 0" @style(['display: none' => $count === 0]) class="inline-flex items-center rounded-full bg-basil text-white shadow">
                                                <button type="button" x-on:click="remove({{ $item->id }})" class="flex h-11 w-11 items-center justify-center" aria-label="{{ __('Remove one') }}: {{ $item->name }}"><x-glyph name="minus" class="h-4 w-4"/></button>
                                                <span x-effect="shown({{ $item->id }}); pop($el)" x-text="shown({{ $item->id }})" class="w-7 text-center font-bold" aria-live="polite">{{ $count }}</span>
                                                <button type="button" x-on:click="add({{ $item->id }})" class="flex h-11 w-11 items-center justify-center" aria-label="{{ __('Add one') }}: {{ $item->name }}"><x-glyph name="plus" class="h-4 w-4"/></button>
                                            </div>
                                            <button type="button" x-show="shown({{ $item->id }}) === 0" @style(['display: none' => $count > 0]) x-on:click="add({{ $item->id }})"
                                                    class="min-h-11 rounded-full bg-tomato px-5 py-2.5 text-sm font-bold text-white shadow transition hover:bg-tomato-dark active:scale-95">
                                                {{ __('Add') }}
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endforeach
    </div>

    <footer class="mt-14 text-center text-xs text-ink/70">
        <div class="tricolore mx-auto mb-3 w-24 rounded"></div>
        <p>{{ __('Please tell your server about any allergies.') }}</p>
        <a href="{{ route('credits') }}" class="underline">{{ __('Photo credits') }}</a>
    </footer>

    {{-- Floating cart bar (instant, driven by the optimistic cart) --}}
    <div x-show="barCount > 0" @style(['display: none' => ! $cartVisible]) class="fixed inset-x-0 bottom-0 z-20 p-3 pb-[max(0.75rem,env(safe-area-inset-bottom))]">
        <button type="button" x-on:click="$dispatch('open-cart')"
                class="mx-auto flex w-full max-w-2xl items-center justify-between rounded-full bg-tomato px-6 py-3.5 font-bold text-white shadow-2xl ring-4 ring-cream">
            <span class="flex items-center gap-3">
                <span x-effect="barCount; pop($el)" x-text="barCount" class="flex h-7 w-7 items-center justify-center rounded-full bg-white text-sm text-tomato" aria-live="polite">{{ $cartCount }}</span>
                {{ __('View order') }}
            </span>
            <span x-text="euro(barCents)">&euro;{{ number_format($cartTotalCents / 100, 2, ',', '.') }}</span>
        </button>
    </div>

    {{-- The popup and the drawer are their own components, so opening them never re-renders the menu. --}}
    <livewire:dish-modal :table-id="$table->id" :client-id="$clientId" />
    <livewire:cart-drawer :table-id="$table->id" :client-id="$clientId" />
</div>
