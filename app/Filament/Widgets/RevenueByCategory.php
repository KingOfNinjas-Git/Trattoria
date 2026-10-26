<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class RevenueByCategory extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Revenue by category';

    protected ?string $description = 'Last 30 days';

    protected ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $rows = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->join('menu_items', 'menu_items.id', '=', 'order_items.menu_item_id')
            ->join('categories', 'categories.id', '=', 'menu_items.category_id')
            ->where('order_items.created_at', '>=', now()->subDays(30))
            ->where('orders.status', '!=', 'cancelled')
            ->selectRaw('categories.name as category, sum(order_items.price_cents * order_items.quantity) as cents')
            ->groupBy('categories.name')
            ->orderByDesc('cents')
            ->get();

        return [
            'datasets' => [[
                'label' => 'Revenue (EUR)',
                'data' => $rows->map(fn ($r) => round($r->cents / 100, 2))->all(),
                'backgroundColor' => ['#c8102e', '#008c45', '#e0a526', '#2b2118', '#9d0c24', '#5fd08e', '#b8a98a'],
            ]],
            'labels' => $rows->pluck('category')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }
}
