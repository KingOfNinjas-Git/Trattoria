<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Models\DiningTable;
use App\Models\Order;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['table', 'items']))
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->columns([
                TextColumn::make('id')
                    ->label('Order')
                    ->prefix('#')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('table.number')
                    ->label('Table')
                    ->sortable(),
                TextColumn::make('dishes')
                    ->label('Dishes')
                    ->state(fn (Order $o) => $o->items->map(fn ($i) => $i->quantity.' x '.$i->name)->implode(', '))
                    ->limit(70)
                    ->searchable(query: fn (Builder $q, string $search) => $q->whereHas('items', fn (Builder $i) => $i->where('name', 'like', "%{$search}%"))),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'received' => 'danger',
                        'preparing' => 'warning',
                        'ready', 'served' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('total_cents')
                    ->label('Total')
                    ->formatStateUsing(fn ($state) => '€'.number_format($state / 100, 2, ',', '.'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Placed')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(array_combine([...Order::STATUSES, Order::CANCELLED], ['Received', 'Preparing', 'Ready', 'Served', 'Cancelled']))
                    ->multiple(),
                SelectFilter::make('dining_table_id')
                    ->label('Table')
                    ->options(fn () => DiningTable::orderBy('number')->pluck('number', 'id')->map(fn ($n) => 'Table '.$n)->all()),
                Filter::make('placed')
                    ->label('Placed between')
                    ->schema([
                        DatePicker::make('from'),
                        DatePicker::make('until'),
                    ])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn (Builder $q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
