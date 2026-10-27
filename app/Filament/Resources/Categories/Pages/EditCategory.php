<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Concerns\DemoReadOnlyEdit;
use App\Filament\Resources\Categories\CategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCategory extends EditRecord
{
    use DemoReadOnlyEdit;

    protected static string $resource = CategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
