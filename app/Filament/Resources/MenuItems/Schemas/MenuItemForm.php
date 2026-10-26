<?php

namespace App\Filament\Resources\MenuItems\Schemas;

use App\Models\MenuItem;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MenuItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dish')
                    ->columns(2)
                    ->schema([
                        Select::make('category_id')
                            ->relationship('category', 'name')
                            ->required(),
                        TextInput::make('name')
                            ->required(),
                        Textarea::make('description')
                            ->label('Description (English)')
                            ->required()
                            ->maxLength(255)
                            ->rows(2)
                            ->columnSpanFull(),
                        TextInput::make('price_cents')
                            ->label('Price')
                            ->prefix('€')
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->formatStateUsing(fn ($state) => $state === null ? null : $state / 100)
                            ->dehydrateStateUsing(fn ($state) => (int) round($state * 100)),
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->required(),
                        FileUpload::make('image')
                            ->label('Photo')
                            ->image()
                            ->imageEditor()
                            ->disk('menu')
                            ->visibility('public')
                            ->maxSize(4096)
                            ->columnSpanFull(),
                    ]),

                Section::make('Dietary information')
                    ->columns(3)
                    ->schema([
                        Toggle::make('is_vegetarian')->label('Vegetarian'),
                        Toggle::make('is_spicy')->label('Spicy'),
                        Toggle::make('is_available')->label('Available')->default(true),
                        CheckboxList::make('allergens')
                            ->options(MenuItem::ALLERGENS)
                            ->columns(3)
                            ->columnSpanFull(),
                    ]),

                Section::make('Options')
                    ->description('Optional choices the customer makes before adding the dish, such as size or extras.')
                    ->collapsed()
                    ->schema([
                        Repeater::make('options')
                            ->label('Option groups')
                            ->addActionLabel('Add option group')
                            ->collapsible()
                            ->schema([
                                TextInput::make('name')->label('Group name')->required(),
                                Select::make('type')
                                    ->options(['single' => 'Pick one', 'multiple' => 'Pick any'])
                                    ->default('single')
                                    ->required(),
                                Toggle::make('required')->label('Required'),
                                Repeater::make('choices')
                                    ->addActionLabel('Add choice')
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('name')->required(),
                                        TextInput::make('price_cents')
                                            ->label('Extra price')
                                            ->prefix('€')
                                            ->numeric()
                                            ->default(0)
                                            ->minValue(0)
                                            ->formatStateUsing(fn ($state) => $state === null ? 0 : $state / 100)
                                            ->dehydrateStateUsing(fn ($state) => (int) round(($state ?? 0) * 100)),
                                    ])
                                    ->minItems(1)
                                    ->defaultItems(1),
                            ]),
                    ]),

                Section::make('Translations')
                    ->description('Customers see these when they switch the menu language. Dish names stay the same.')
                    ->collapsed()
                    ->schema([
                        Textarea::make('translations.it.description')->label('Description (Italiano)')->rows(2)->maxLength(255),
                        Textarea::make('translations.pt.description')->label('Description (Português)')->rows(2)->maxLength(255),
                    ]),
            ]);
    }
}
