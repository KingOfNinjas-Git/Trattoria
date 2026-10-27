<?php

namespace App\Filament\Concerns;

/** For an EditRecord page: "Save changes" saves nothing in the live demo. */
trait DemoReadOnlyEdit
{
    use DemoReadOnly;

    public function save(bool $shouldRedirect = true, bool $shouldSendSavedNotification = true): void
    {
        if ($this->blockedInDemo()) {
            return;
        }

        parent::save($shouldRedirect, $shouldSendSavedNotification);
    }
}
