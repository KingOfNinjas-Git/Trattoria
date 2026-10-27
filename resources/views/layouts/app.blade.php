<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ $title ?? 'Trattoria Bella Vita' }}</title>
    @php
        $shareTitle = ($title ?? null) ? $title.' | Trattoria Bella Vita' : 'Trattoria Bella Vita: QR ordering for restaurants';
        $shareText = 'Scan the code on your table, browse the menu and order from your phone. A portfolio demo by Francisco Cavaco.';
        $shareImage = url('/trattoria-share.jpg?v=1');
    @endphp
    <meta name="description" content="{{ $shareText }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Trattoria Bella Vita">
    <meta property="og:title" content="{{ $shareTitle }}">
    <meta property="og:description" content="{{ $shareText }}">
    <meta property="og:url" content="{{ url('/') }}">
    <meta property="og:image" content="{{ $shareImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta property="og:image:alt" content="Trattoria Bella Vita: a phone showing the menu in front of the kitchen board">
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $shareTitle }}">
    <meta name="twitter:description" content="{{ $shareText }}">
    <meta name="twitter:image" content="{{ $shareImage }}">
    <link rel="icon" href="/favicon.ico?v=2" sizes="any">
    <link rel="icon" type="image/png" sizes="192x192" href="/icon-192.png?v=2">
    <link rel="apple-touch-icon" href="/apple-touch-icon.png?v=2">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="preload" as="style" onload="this.onload=null;this.rel='stylesheet'" href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Playfair+Display:ital,wght@0,600;0,700;1,500&display=swap">
    <noscript><link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Playfair+Display:ital,wght@0,600;0,700;1,500&display=swap" rel="stylesheet"></noscript>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased">
    <div class="tricolore"></div>
    @if (config('trattoria.demo'))
        <x-demo-bar/>
    @endif
    <main id="main-content">
        {{ $slot }}
    </main>

    @stack('scripts')

    {{-- Toast: any Livewire component can `dispatch('toast', message: '...')` --}}
    <div x-data="{ show: false, message: '', timer: null }"
         x-on:toast.window="message = $event.detail.message; show = true; clearTimeout(timer); timer = setTimeout(() => show = false, 2600)"
         x-cloak x-show="show" x-transition.opacity.duration.200ms
         class="pointer-events-none fixed inset-x-0 top-4 z-50 flex justify-center px-4" role="status" aria-live="polite">
        <div class="flex items-center gap-2 rounded-full bg-ink px-5 py-2.5 text-sm font-bold text-white shadow-xl">
            <x-glyph name="check" class="h-4 w-4 text-basil-light"/>
            <span x-text="message"></span>
        </div>
    </div>
</body>
</html>
