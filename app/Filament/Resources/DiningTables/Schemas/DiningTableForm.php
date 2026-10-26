<?php

namespace App\Filament\Resources\DiningTables\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class DiningTableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('number')
                    ->required()
                    ->numeric(),
                TextInput::make('token')
                    ->helperText('Leave blank to generate one automatically.')
                    ->unique(ignoreRecord: true),
            ]);
    }
}
