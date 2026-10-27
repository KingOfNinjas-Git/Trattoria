<?php

namespace App\Filament\Resources\DiningTables\Pages;

use App\Filament\Resources\DiningTables\DiningTableResource;
use Filament\Actions\DeleteAction;
use App\Filament\Concerns\DemoReadOnlyEdit;
use Filament\Resources\Pages\EditRecord;

class EditDiningTable extends EditRecord
{
    use DemoReadOnlyEdit;

    protected static string $resource = DiningTableResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
