<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '15s';

    protected function getStats(): array
    {
        $today = Order::notCancelled()->whereDate('created_at', today());
        $count = (clone $today)->count();
        $revenue = (int) (clone $today)->sum('total_cents');

        // Orders per day over the last 7 days, used as sparklines.
        $week = collect(range(6, 0))->map(fn (int $daysAgo) => today()->subDays($daysAgo));
        $ordersPerDay = $week->map(fn ($d) => Order::notCancelled()->whereDate('created_at', $d)->count())->all();
        $revenuePerDay = $week->map(fn ($d) => (int) Order::notCancelled()->whereDate('created_at', $d)->sum('total_cents') / 100)->all();

        $avgPrepMinutes = Order::notCancelled()->whereNotNull('ready_at')
            ->where('created_at', '>=', now()->subDays(7))
            ->get(['created_at', 'ready_at'])
            ->avg(fn (Order $o) => $o->created_at->diffInSeconds($o->ready_at) / 60);

        return [
            Stat::make('Orders today', $count)
                ->description('Trend over the last 7 days')
                ->chart($ordersPerDay)
                ->color('danger'),
            Stat::make('Revenue today', $this->euro($revenue))
                ->chart($revenuePerDay)
                ->color('success'),
            Stat::make('Average order', $this->euro($count ? intdiv($revenue, $count) : 0))
                ->description('Today'),
            Stat::make('Average prep time', $avgPrepMinutes === null ? 'n/a' : round($avgPrepMinutes).' min')
                ->description('Order placed to ready, last 7 days'),
        ];
    }

    private function euro(int $cents): string
    {
        return '€'.number_format($cents / 100, 2, ',', '.');
    }
}
