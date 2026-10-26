<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ticket #{{ $order->id }}</title>
    <style>
        /* Sized for an 80 mm receipt printer; also fine on A4. */
        @page { size: 80mm auto; margin: 4mm; }
        body { font-family: "Courier New", monospace; font-size: 14px; color: #000; width: 72mm; margin: 0 auto; }
        h1 { font-size: 20px; margin: 0; text-align: center; }
        .center { text-align: center; }
        .rule { border-top: 1px dashed #000; margin: 8px 0; }
        .big { font-size: 26px; font-weight: bold; text-align: center; margin: 6px 0; }
        .row { display: flex; gap: 8px; margin: 4px 0; }
        .qty { font-weight: bold; min-width: 28px; }
        .opt { font-size: 12px; margin-left: 36px; }
        .note { border: 1px solid #000; padding: 4px; margin-top: 6px; }
        .actions { text-align: center; margin: 16px 0; }
        button { font: inherit; padding: 8px 16px; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
    <h1>Trattoria Bella Vita</h1>
    <p class="center">Order #{{ $order->id }} &middot; {{ $order->created_at->format('H:i') }}</p>
    <div class="big">TABLE {{ $order->table->number }}</div>
    <div class="rule"></div>

    @foreach ($order->items as $item)
        <div class="row"><span class="qty">{{ $item->quantity }} x</span><span>{{ $item->name }}</span></div>
        @if ($item->options_text)<div class="opt">{{ $item->options_text }}</div>@endif
    @endforeach

    @if ($order->notes)
        <div class="rule"></div>
        <div class="note">NOTE: {{ $order->notes }}</div>
    @endif

    <div class="rule"></div>
    <p class="center">{{ now()->format('d/m/Y H:i') }}</p>

    <div class="actions"><button onclick="window.print()">Print</button></div>
    <script>
        // Open the print dialog straight away when the page was opened from the kitchen board.
        if (location.search.includes('print=1')) window.addEventListener('load', () => window.print());
    </script>
</body>
</html>
