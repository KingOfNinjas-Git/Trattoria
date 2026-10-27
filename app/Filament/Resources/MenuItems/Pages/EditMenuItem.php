<?php

namespace App\Filament\Resources\MenuItems\Pages;

use App\Filament\Resources\MenuItems\MenuItemResource;
use Filament\Actions\DeleteAction;
use App\Filament\Concerns\DemoReadOnlyEdit;
use Filament\Resources\Pages\EditRecord;

class EditMenuItem extends EditRecord
{
    use DemoReadOnlyEdit;

    protected static string $resource = MenuItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
