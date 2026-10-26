<?php

namespace App\Filament\Resources\Orders\Schemas;

use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Order')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('id')->label('Order')->prefix('#'),
                        TextEntry::make('table.number')->label('Table'),
                        TextEntry::make('status')->badge()->color(fn (string $state) => match ($state) {
                            'received' => 'danger',
                            'preparing' => 'warning',
                            'ready', 'served' => 'success',
                            default => 'gray',
                        }),
                        TextEntry::make('total_cents')->label('Total')
                            ->formatStateUsing(fn ($state) => '€'.number_format($state / 100, 2, ',', '.')),
                        TextEntry::make('notes')->placeholder('No notes')->columnSpanFull(),
                    ]),

                Section::make('Dishes')
                    ->schema([
                        RepeatableEntry::make('items')
                            ->hiddenLabel()
                            ->columns(4)
                            ->schema([
                                TextEntry::make('name')->columnSpan(2),
                                TextEntry::make('options_text')->label('Options')->placeholder('None'),
                                TextEntry::make('quantity')->label('Quantity')->prefix('x '),
                            ]),
                    ]),

                Section::make('Timeline')
                    ->columns(4)
                    ->schema([
                        TextEntry::make('created_at')->label('Placed')->dateTime('d M Y, H:i:s'),
                        TextEntry::make('preparing_at')->label('Preparing')->dateTime('H:i:s')->placeholder('Not yet'),
                        TextEntry::make('ready_at')->label('Ready')->dateTime('H:i:s')->placeholder('Not yet'),
                        TextEntry::make('served_at')->label('Served')->dateTime('H:i:s')->placeholder('Not yet'),
                    ]),
            ]);
    }
}
