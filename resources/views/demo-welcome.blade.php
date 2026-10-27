{{-- Demo only: the first page a visitor sees. Explains the idea, shows the table's QR code for people on a
     phone, and a big button for people on a computer or who cannot scan. --}}
@component('layouts.app', ['title' => __('Try the demo')])
<div class="mx-auto max-w-4xl px-4 py-10 sm:py-14">
    <div class="text-center">
        <p class="text-sm font-bold uppercase tracking-widest text-basil-dark">{{ __('Live demo') }}</p>
        <h1 class="font-display mt-2 text-4xl font-bold text-tomato sm:text-5xl">Trattoria Bella Vita</h1>
        <p class="mx-auto mt-4 max-w-xl text-lg text-ink/80">
            {{ __('A QR ordering system for restaurants: guests scan the code on their table, order from their phone, and the kitchen sees it straight away.') }}
        </p>
    </div>

    <div class="mt-10 grid gap-6 md:grid-cols-2">
        <section class="flex flex-col items-center rounded-3xl bg-white p-6 text-center shadow-sm ring-1 ring-ink/5" aria-labelledby="scan-title">
            <h2 id="scan-title" class="font-display text-2xl font-bold">{{ __('On a phone? Scan the code') }}</h2>
            <p class="mt-1 text-sm text-ink/70">{{ __('Table :number', ['number' => $table->number]) }}</p>
            <div class="mt-4 rounded-2xl border-4 border-tomato p-3" role="img" aria-label="{{ __('QR code that opens the menu of table :number', ['number' => $table->number]) }}">
                {!! QrCode::size(200)->margin(0)->color(43, 33, 24)->generate($table->url()) !!}
            </div>
            <p class="mt-4 text-sm text-ink/70">{{ __('Point your camera at the code, just like a guest would at the restaurant.') }}</p>
        </section>

        <section class="flex flex-col items-center justify-center rounded-3xl bg-basil p-6 text-center text-white shadow-sm" aria-labelledby="here-title">
            <h2 id="here-title" class="font-display text-2xl font-bold">{{ __('On a computer, or cannot scan?') }}</h2>
            <p class="mt-2 max-w-xs text-white/90">{{ __('No problem. Open the very same menu right here and try everything.') }}</p>
            <a href="{{ $table->url() }}" class="mt-6 inline-flex items-center justify-center rounded-full bg-white px-8 py-4 text-lg font-bold text-basil-dark shadow-md transition hover:bg-cream focus-visible:outline focus-visible:outline-4 focus-visible:outline-offset-2 focus-visible:outline-white">
                {{ __('Open the menu on this device') }}
            </a>
            <p class="mt-4 text-sm text-white/85">{{ __('Same experience, nothing missing.') }}</p>
        </section>
    </div>

    <section class="mt-10" aria-labelledby="flow-title">
        <h2 id="flow-title" class="font-display text-center text-2xl font-bold">{{ __('Then see the other side') }}</h2>
        <ol class="mt-5 grid gap-4 sm:grid-cols-3">
            <li class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink/5">
                <p class="font-bold text-tomato">1. {{ __('Place an order') }}</p>
                <p class="mt-1 text-sm text-ink/75">{{ __('Add dishes, send the order, call a waiter or ask for the bill.') }}</p>
            </li>
            <li class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink/5">
                <p class="font-bold text-tomato">2. {{ __('Watch it reach the kitchen') }}</p>
                <p class="mt-1 text-sm text-ink/75">{{ __('The cooks get the ticket and move it from received to ready.') }}</p>
                <a href="{{ url('/demo/kitchen') }}" class="mt-3 inline-block rounded-full bg-ink px-4 py-2 text-sm font-bold text-white hover:bg-ink/85">{{ __('Open the kitchen board') }}</a>
            </li>
            <li class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-ink/5">
                <p class="font-bold text-tomato">3. {{ __('Run the restaurant') }}</p>
                <p class="mt-1 text-sm text-ink/75">{{ __('See sales, popular dishes, the menu and the tables in the admin panel.') }}</p>
                <a href="{{ url('/demo/admin') }}" class="mt-3 inline-block rounded-full bg-ink px-4 py-2 text-sm font-bold text-white hover:bg-ink/85">{{ __('Open the admin panel') }}</a>
            </li>
        </ol>
    </section>
</div>
@endcomponent
