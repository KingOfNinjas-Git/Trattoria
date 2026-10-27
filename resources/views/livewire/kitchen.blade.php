{{-- Kitchen board: live via Reverb, slow polling (every 20 s) as a fallback. --}}
<div wire:poll.{{ config('trattoria.poll.kitchen') }}s
     x-data="{
        dark: (() => { try { return localStorage.getItem('kitchen-dark') === '1'; } catch (e) { return false; } })(),
        toggleDark() { this.dark = !this.dark; try { localStorage.setItem('kitchen-dark', this.dark ? '1' : '0'); } catch (e) {} },
        now: Date.now(),

        // Sound: browsers only allow audio after a tap, so the first tap on the banner switches it on.
        ctx: null,
        soundReady: false,
        enableSound() {
            const AC = window.AudioContext || window.webkitAudioContext;
            if (!AC) return;
            this.ctx = this.ctx || new AC();
            this.ctx.resume().then(() => { this.soundReady = this.ctx.state === 'running'; if (this.soundReady) this.chime('order.placed'); });
        },
        tone(freq, start, length, volume = 0.18) {
            const o = this.ctx.createOscillator();
            const g = this.ctx.createGain();
            o.type = 'sine';
            o.frequency.value = freq;
            g.gain.setValueAtTime(0.0001, this.ctx.currentTime + start);
            g.gain.exponentialRampToValueAtTime(volume, this.ctx.currentTime + start + 0.02);
            g.gain.exponentialRampToValueAtTime(0.0001, this.ctx.currentTime + start + length);
            o.connect(g).connect(this.ctx.destination);
            o.start(this.ctx.currentTime + start);
            o.stop(this.ctx.currentTime + start + length + 0.05);
        },
        // New order: a rising two-note chime. Waiter or bill request: three quick lower notes.
        chime(kind) {
            if (!this.ctx || this.ctx.state !== 'running') return;
            if (kind === 'service') { [523, 523, 523].forEach((f, i) => this.tone(f, i * 0.16, 0.14)); return; }
            this.tone(659, 0, 0.35);
            this.tone(880, 0.18, 0.55);
        },

        // Live connection indicator.
        link: '{{ config('trattoria.realtime') === 'reverb' ? 'connecting' : 'polling' }}',
        watchLink(tries = 0) {
            if (this.link === 'polling') return;
            const conn = window.Echo?.connector?.pusher?.connection;
            if (!conn) { if (tries < 40) setTimeout(() => this.watchLink(tries + 1), 100); else this.link = 'offline'; return; }
            const map = { connected: 'live', connecting: 'connecting', unavailable: 'offline', failed: 'offline', disconnected: 'offline' };
            this.link = map[conn.state] ?? 'connecting';
            conn.bind('state_change', (s) => { this.link = map[s.current] ?? 'connecting'; });
        },

        elapsed(ts) {
            const s = Math.max(0, Math.floor((this.now - ts * 1000) / 1000));
            return String(Math.floor(s / 60)).padStart(2, '0') + ':' + String(s % 60).padStart(2, '0');
        },
        late(ts) { return (this.now - ts * 1000) / 60000 >= {{ \App\Models\Order::LATE_AFTER_MINUTES }}; },
     }"
     x-init="setInterval(() => now = Date.now(), 1000); watchLink()"
     x-on:new-order.window="chime($event.detail.kind)"
     x-bind:class="dark ? 'dark' : ''">
    <div class="min-h-screen bg-cream p-4 text-ink transition-colors dark:bg-neutral-900 dark:text-neutral-100">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="font-display text-3xl font-bold text-tomato dark:text-red-400">{{ __('Kitchen') }}</h1>
                <span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-ink/70 shadow-sm dark:bg-neutral-800 dark:text-neutral-300">{{ $orders->count() }} {{ __('active') }}</span>
                {{-- Live connection state --}}
                <span class="inline-flex items-center gap-1.5 rounded-full bg-white px-3 py-1 text-xs font-bold shadow-sm dark:bg-neutral-800" role="status">
                    <span class="h-2 w-2 rounded-full"
                          x-bind:class="{ 'bg-basil': link === 'live' || link === 'polling', 'bg-amber-500 animate-pulse': link === 'connecting', 'bg-tomato': link === 'offline' }"></span>
                    <span x-show="link === 'live'">Live</span>
                    <span x-show="link === 'connecting'" x-cloak>Connecting...</span>
                    <span x-show="link === 'offline'" x-cloak>Reconnecting, refreshing every 20 s</span>
                    <span x-show="link === 'polling'" x-cloak>Auto-refresh every {{ config('trattoria.poll.kitchen') }} s</span>
                </span>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="inline-flex rounded-full bg-white p-0.5 text-sm font-bold shadow-sm dark:bg-neutral-800" role="tablist">
                    <button wire:click="$set('tab', 'orders')" role="tab" aria-selected="{{ $tab === 'orders' ? 'true' : 'false' }}"
                            class="rounded-full px-4 py-1.5 transition {{ $tab === 'orders' ? 'bg-basil text-white' : 'text-ink/70 dark:text-neutral-300' }}">Orders</button>
                    <button wire:click="$set('tab', 'menu')" role="tab" aria-selected="{{ $tab === 'menu' ? 'true' : 'false' }}"
                            class="rounded-full px-4 py-1.5 transition {{ $tab === 'menu' ? 'bg-basil text-white' : 'text-ink/70 dark:text-neutral-300' }}">Menu availability</button>
                </div>
                <button type="button" x-on:click="toggleDark()" class="flex h-10 w-10 items-center justify-center rounded-full bg-white shadow-sm dark:bg-neutral-800" aria-label="Toggle dark mode">
                    <x-glyph name="moon" class="h-4 w-4 dark:hidden"/>
                    <x-glyph name="sun" class="hidden h-4 w-4 dark:block"/>
                </button>
                <a href="/admin" class="rounded-full bg-white px-4 py-2 text-sm font-bold text-ink/70 shadow-sm dark:bg-neutral-800 dark:text-neutral-300">Admin</a>
            </div>
        </div>

        {{-- Sound banner: shown until a tap has unlocked audio --}}
        <button type="button" x-show="!soundReady" x-cloak x-on:click="enableSound()"
                class="mb-4 flex w-full items-center justify-center gap-2 rounded-xl border border-dashed border-amber-500 bg-amber-50 px-4 py-3 text-sm font-bold text-amber-900 dark:bg-amber-950 dark:text-amber-100">
            <x-glyph name="bell" class="h-4 w-4"/>
            Tap here to turn on sound alerts for new orders and waiter calls
        </button>

        @if ($tab === 'orders')
            {{-- Service requests --}}
            @if ($requests->isNotEmpty())
                <div class="mb-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($requests as $request)
                        <div wire:key="req-{{ $request->id }}"
                             class="flex items-center gap-3 rounded-xl border-l-8 bg-white p-3 shadow dark:bg-neutral-800 {{ $request->type === 'bill' ? 'border-basil' : 'border-amber-500' }}">
                            <x-glyph :name="$request->type === 'bill' ? 'receipt' : 'bell'" class="h-6 w-6 shrink-0 {{ $request->type === 'bill' ? 'text-basil' : 'text-amber-500' }}"/>
                            <div class="min-w-0 flex-1">
                                <p class="font-bold">Table {{ $request->table->number }}: {{ $request->type === 'bill' ? 'wants the bill' : 'needs a waiter' }}</p>
                                @if ($request->type === 'bill' && $request->session)
                                    <p class="text-sm text-ink/70 dark:text-neutral-400">Total &euro;{{ number_format($request->session->totalCents() / 100, 2, ',', '.') }}</p>
                                @elseif ($request->acknowledged_at)
                                    <p class="text-sm text-ink/70 dark:text-neutral-400">Acknowledged, guest has been told</p>
                                @endif
                            </div>
                            <div class="flex shrink-0 flex-col gap-1.5">
                                @if (! $request->acknowledged_at)
                                    <button wire:click="acknowledge({{ $request->id }})" class="rounded-full border border-amber-500 px-3 py-1.5 text-xs font-bold text-amber-700 hover:bg-amber-500 hover:text-white dark:text-amber-300">On my way</button>
                                @endif
                                @if ($request->type === 'bill' && $request->session)
                                    <button wire:click="closeTable({{ $request->session->id }})" wire:confirm="Close table {{ $request->table->number }}? This ends the current session."
                                            class="rounded-full bg-basil px-3 py-1.5 text-xs font-bold text-white hover:bg-basil-dark">Paid, close table</button>
                                @else
                                    <button wire:click="resolveRequest({{ $request->id }})" class="rounded-full bg-amber-500 px-3 py-1.5 text-xs font-bold text-white hover:bg-amber-600">Done</button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Sort --}}
            <div class="mb-3 flex items-center gap-2 text-xs font-bold text-ink/70 dark:text-neutral-400">
                <span>Sort</span>
                <div class="inline-flex rounded-full bg-white p-0.5 shadow-sm dark:bg-neutral-800">
                    <button wire:click="$set('sort', 'age')" aria-pressed="{{ $sort === 'age' ? 'true' : 'false' }}" class="rounded-full px-3 py-1 {{ $sort === 'age' ? 'bg-basil text-white' : '' }}">Oldest first</button>
                    <button wire:click="$set('sort', 'stage')" aria-pressed="{{ $sort === 'stage' ? 'true' : 'false' }}" class="rounded-full px-3 py-1 {{ $sort === 'stage' ? 'bg-basil text-white' : '' }}">By stage</button>
                </div>
            </div>

            @if ($orders->isEmpty())
                <p class="font-display py-24 text-center text-xl italic text-ink/75 dark:text-neutral-400">No orders right now...</p>
            @endif

            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($orders as $order)
                    @php
                        $colors = ['received' => 'border-tomato', 'preparing' => 'border-amber-500', 'ready' => 'border-basil'];
                        $labels = ['received' => 'Start preparing', 'preparing' => 'Mark ready', 'ready' => 'Mark served'];
                        $total = $order->items->count();
                        $ready = $order->readyItemsCount();
                    @endphp
                    <div wire:key="order-{{ $order->id }}" class="flex flex-col rounded-xl border-t-8 bg-white p-4 shadow dark:bg-neutral-800 {{ $colors[$order->status] }}">
                        <div class="flex items-baseline justify-between gap-2">
                            <h2 class="font-display text-2xl font-bold">Table {{ $order->table->number }}</h2>
                            <span class="flex items-center gap-2 text-xs text-ink/70 dark:text-neutral-400">
                                #{{ $order->id }}
                                <a href="{{ route('kitchen.ticket', $order) }}?print=1" target="_blank" rel="noopener" class="rounded-full border border-ink/15 px-2 py-0.5 font-bold hover:bg-ink/5 dark:border-neutral-600" aria-label="Print ticket for order {{ $order->id }}">Print</a>
                            </span>
                        </div>

                        <div class="mt-1 flex items-center justify-between text-xs font-bold uppercase tracking-wide">
                            <span class="text-ink/70 dark:text-neutral-400">{{ $order->status }}@if ($order->status === 'preparing' && $total > 1) &middot; {{ $ready }}/{{ $total }} ready @endif</span>
                            <span class="inline-flex items-center gap-1 tabular-nums"
                                  x-bind:class="late({{ $order->created_at->timestamp }}) && '{{ $order->status }}' !== 'ready' ? 'text-tomato dark:text-red-400' : 'text-ink/70 dark:text-neutral-400'">
                                <x-glyph name="clock" class="h-3.5 w-3.5"/>
                                <span x-text="elapsed({{ $order->created_at->timestamp }})">--:--</span>
                                <template x-if="late({{ $order->created_at->timestamp }}) && '{{ $order->status }}' !== 'ready'"><span>(late)</span></template>
                            </span>
                        </div>

                        <ul class="my-3 flex-1 space-y-1.5">
                            @foreach ($order->items as $item)
                                <li wire:key="oi-{{ $item->id }}">
                                    <button type="button" wire:click="toggleItem({{ $item->id }})" aria-pressed="{{ $item->is_ready ? 'true' : 'false' }}"
                                            class="flex w-full items-start gap-2 rounded text-left focus:outline-none focus-visible:ring-2 focus-visible:ring-basil">
                                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded border-2 {{ $item->is_ready ? 'border-basil bg-basil text-white' : 'border-ink/30 dark:border-neutral-500' }}">
                                            @if ($item->is_ready)<x-glyph name="check" class="h-3 w-3"/>@endif
                                        </span>
                                        <span class="font-bold {{ $item->is_ready ? 'line-through opacity-50' : '' }}">
                                            <span class="text-tomato dark:text-red-400">{{ $item->quantity }} x</span> {{ $item->name }}
                                            @if ($item->options_text)<span class="block text-xs font-normal text-ink/70 dark:text-neutral-400">{{ $item->options_text }}</span>@endif
                                        </span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>

                        @if ($order->notes)
                            <p class="mb-3 rounded border border-amber-300 bg-amber-50 p-2 text-sm text-ink dark:border-amber-700 dark:bg-amber-950 dark:text-amber-100">{{ $order->notes }}</p>
                        @endif

                        <button wire:click="advance({{ $order->id }})"
                                class="rounded-full bg-basil py-2 font-bold text-white transition hover:bg-basil-dark">{{ $labels[$order->status] }}</button>

                        <div class="mt-2 flex justify-between text-xs font-bold">
                            @if ($order->status !== 'received')
                                <button wire:click="undo({{ $order->id }})" class="rounded px-1 py-1 text-ink/70 underline hover:text-ink dark:text-neutral-400 dark:hover:text-neutral-100">Undo last step</button>
                            @else
                                <span></span>
                            @endif
                            <button wire:click="cancel({{ $order->id }})" wire:confirm="Cancel order #{{ $order->id }} for table {{ $order->table->number }}? The guest will see it as cancelled."
                                    class="rounded px-1 py-1 text-tomato underline hover:text-tomato-dark dark:text-red-400">Cancel order</button>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($recentlyServed->isNotEmpty())
                <section class="mt-8" aria-label="Recently served">
                    <h2 class="mb-2 text-sm font-bold uppercase tracking-wide text-ink/70 dark:text-neutral-400">Recently served</h2>
                    <ul class="divide-y divide-ink/10 rounded-xl bg-white shadow dark:divide-neutral-700 dark:bg-neutral-800">
                        @foreach ($recentlyServed as $done)
                            <li class="flex items-center justify-between px-4 py-2 text-sm" wire:key="done-{{ $done->id }}">
                                <span>Table {{ $done->table->number }} &middot; order #{{ $done->id }} &middot; {{ $done->served_at->diffForHumans(short: true) }}</span>
                                <button wire:click="undo({{ $done->id }})" class="font-bold text-ink/70 underline hover:text-ink dark:text-neutral-400">Bring back</button>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        @else
            {{-- Menu availability --}}
            <p class="mb-4 text-sm text-ink/70 dark:text-neutral-400">Switch a dish off when the kitchen runs out. Open customer menus update immediately.</p>
            <div class="grid gap-6 lg:grid-cols-2">
                @foreach ($categories as $category)
                    <section class="rounded-xl bg-white p-4 shadow dark:bg-neutral-800">
                        <h2 class="font-display mb-2 text-xl font-bold text-tomato dark:text-red-400">{{ $category->name }}</h2>
                        <ul class="divide-y divide-ink/10 dark:divide-neutral-700">
                            @foreach ($category->items as $item)
                                <li class="flex items-center justify-between py-2" wire:key="avail-{{ $item->id }}">
                                    <span class="{{ $item->is_available ? '' : 'text-ink/70 line-through dark:text-neutral-400' }}">{{ $item->name }}</span>
                                    <button wire:click="toggleAvailability({{ $item->id }})" role="switch" aria-checked="{{ $item->is_available ? 'true' : 'false' }}" aria-label="Available: {{ $item->name }}"
                                            class="relative h-6 w-11 rounded-full transition {{ $item->is_available ? 'bg-basil' : 'bg-ink/30 dark:bg-neutral-600' }}">
                                        <span class="absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-all {{ $item->is_available ? 'left-[22px]' : 'left-0.5' }}"></span>
                                    </button>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
</div>
