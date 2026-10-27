<?php

namespace App\Filament\Concerns;

/** For a CreateRecord page: "Create" saves nothing in the live demo. */
trait DemoReadOnlyCreate
{
    use DemoReadOnly;

    public function create(bool $another = false): void
    {
        if ($this->blockedInDemo()) {
            return;
        }

        parent::create($another);
    }
}
