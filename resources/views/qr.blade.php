<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Table QR codes</title>
    <link href="https://fonts.googleapis.com/css2?family=Lato:wght@400;700&family=Playfair+Display:wght@700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css'])
    <style>
        @media print { .no-print { display: none; } .card { break-inside: avoid; } }
    </style>
</head>
<body class="font-sans p-6">
    <div class="no-print mb-6 text-center">
        <button onclick="window.print()" class="rounded-full bg-tomato text-white font-bold px-6 py-2">Print</button>
        <p class="text-sm text-ink/60 mt-2">Each code opens that table's menu. Make sure APP_URL (or the address you opened this page from) is reachable from the customers' phones.</p>
    </div>

    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($tables as $table)
            <div class="card bg-white rounded-2xl border-4 border-tomato p-6 text-center">
                <div class="tricolore mb-3"></div>
                <p class="font-display text-2xl text-tomato font-bold">Trattoria Bella Vita</p>
                <p class="text-basil-dark font-bold mb-3">Table {{ $table->number }}</p>
                <div class="inline-block">{!! QrCode::size(220)->margin(1)->color(43, 33, 24)->generate($table->url()) !!}</div>
                <p class="mt-3 font-bold">Scan to order</p>
                <p class="text-xs text-ink/50">Scansiona per ordinare</p>
            </div>
        @endforeach
    </div>
</body>
</html>
