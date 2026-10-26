<?php

namespace App\Filament\Widgets;

use App\Models\OrderItem;
use Filament\Widgets\ChartWidget;

class TopDishes extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Top dishes';

    protected ?string $description = 'Portions sold in the last 30 days';

    protected ?string $pollingInterval = '60s';

    protected function getData(): array
    {
        $top = OrderItem::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->whereHas('order', fn ($q) => $q->notCancelled())
            ->selectRaw('name, sum(quantity) as portions')
            ->groupBy('name')
            ->orderByDesc('portions')
            ->limit(8)
            ->get();

        return [
            'datasets' => [[
                'label' => 'Portions',
                'data' => $top->pluck('portions')->map(fn ($p) => (int) $p)->all(),
                'backgroundColor' => '#008c45',
                'borderRadius' => 4,
            ]],
            'labels' => $top->pluck('name')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
