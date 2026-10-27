<?php

namespace App\Filament\Resources\DiningTables\Pages;

use App\Filament\Resources\DiningTables\DiningTableResource;
use App\Filament\Concerns\DemoReadOnlyCreate;
use Filament\Resources\Pages\CreateRecord;

class CreateDiningTable extends CreateRecord
{
    use DemoReadOnlyCreate;

    protected static string $resource = DiningTableResource::class;
}
