<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestOrders extends TableWidget
{
    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Latest orders';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Order::query()->with(['table', 'items'])->latest())
            ->poll('20s')
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('id')->label('Order')->prefix('#'),
                TextColumn::make('table.number')->label('Table'),
                TextColumn::make('items_summary')
                    ->label('Dishes')
                    ->state(fn (Order $o) => $o->items->map(fn ($i) => $i->quantity.' x '.$i->name)->implode(', '))
                    ->limit(60),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'received' => 'danger',
                        'preparing' => 'warning',
                        'ready', 'served' => 'success',
                        'cancelled' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => '€'.number_format($state / 100, 2, ',', '.')),
                TextColumn::make('created_at')->label('Placed')->since(),
            ]);
    }
}
