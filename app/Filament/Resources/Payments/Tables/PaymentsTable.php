<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Models\Payment;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        $euro = fn ($state) => '€'.number_format($state / 100, 2, ',', '.');

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('session.table'))
            ->defaultSort('paid_at', 'desc')
            ->striped()
            ->columns([
                TextColumn::make('paid_at')->label('Paid')->dateTime('d M Y, H:i')->sortable(),
                TextColumn::make('session.table.number')->label('Table'),
                TextColumn::make('label')->label('Who / what')->placeholder('Payment')->searchable(),
                TextColumn::make('amount_cents')->label('Bill part')->formatStateUsing($euro)->sortable(),
                TextColumn::make('tip_cents')->label('Tip')->formatStateUsing($euro)->sortable(),
                TextColumn::make('total')
                    ->label('Charged')
                    ->state(fn (Payment $p) => $euro($p->amount_cents + $p->tip_cents)),
                TextColumn::make('card')
                    ->label('Card')
                    ->state(fn (Payment $p) => trim(($p->card_brand ?? '').' •••• '.($p->card_last4 ?? ''))),
                TextColumn::make('reference')->label('Receipt')->searchable()->toggleable(),
            ]);
    }
}
