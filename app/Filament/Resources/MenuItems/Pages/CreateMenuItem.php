<?php

namespace App\Filament\Resources\MenuItems\Pages;

use App\Filament\Concerns\DemoReadOnlyCreate;
use App\Filament\Resources\MenuItems\MenuItemResource;
use Filament\Resources\Pages\CreateRecord;

class CreateMenuItem extends CreateRecord
{
    use DemoReadOnlyCreate;

    protected static string $resource = MenuItemResource::class;
}
