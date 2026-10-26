<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;

class OrdersOverTime extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Orders over time';

    protected ?string $pollingInterval = '30s';

    public ?string $filter = 'today';

    protected function getFilters(): ?array
    {
        return ['today' => 'Today, by hour', '7d' => 'Last 7 days', '30d' => 'Last 30 days'];
    }

    protected function getData(): array
    {
        if ($this->filter === 'today') {
            $orders = Order::notCancelled()->whereDate('created_at', today())->get(['created_at']);
            $hours = range(10, 23); // opening hours
            $labels = array_map(fn ($h) => sprintf('%02d:00', $h), $hours);
            $values = array_map(fn ($h) => $orders->filter(fn ($o) => $o->created_at->hour === $h)->count(), $hours);
        } else {
            $days = $this->filter === '30d' ? 30 : 7;
            $range = collect(range($days - 1, 0))->map(fn (int $d) => today()->subDays($d));
            $orders = Order::notCancelled()->where('created_at', '>=', $range->first())->get(['created_at']);
            $labels = $range->map(fn ($d) => $d->format('d M'))->all();
            $values = $range->map(fn ($d) => $orders->filter(fn ($o) => $o->created_at->isSameDay($d))->count())->all();
        }

        return [
            'datasets' => [[
                'label' => 'Orders',
                'data' => array_values($values),
                'backgroundColor' => '#c8102e',
                'borderRadius' => 4,
            ]],
            'labels' => array_values($labels),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
