<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Category')
                    ->columns(3)
                    ->schema([
                        TextInput::make('name')
                            ->label('Name (English)')
                            ->required(),
                        TextInput::make('subtitle')
                            ->helperText('Shown next to the name, e.g. the Italian term.'),
                        TextInput::make('sort_order')
                            ->required()
                            ->numeric()
                            ->default(0),
                    ]),
                Section::make('Opening hours for this section')
                    ->description('Leave both empty to serve it all day. Outside these hours the section stays visible but cannot be ordered, for example a dinner-only pizza menu. A window like 18:00 to 02:00 is fine.')
                    ->columns(2)
                    ->schema([
                        TimePicker::make('available_from')->label('From')->seconds(false),
                        TimePicker::make('available_until')->label('Until')->seconds(false),
                    ]),
                Section::make('Translations')
                    ->collapsed()
                    ->columns(2)
                    ->schema([
                        TextInput::make('translations.it.name')->label('Name (Italiano)'),
                        TextInput::make('translations.it.subtitle')->label('Subtitle (Italiano)'),
                        TextInput::make('translations.pt.name')->label('Name (Português)'),
                        TextInput::make('translations.pt.subtitle')->label('Subtitle (Português)'),
                    ]),
            ]);
    }
}
