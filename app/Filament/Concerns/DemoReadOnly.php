<?php

namespace App\Filament\Concerns;

use App\Support\DemoMode;
use Filament\Actions\Action;

/**
 * Live demo: the admin panel is open to everyone, so menu items, categories and tables cannot be changed.
 * The save button does nothing except open a box that explains why.
 */
trait DemoReadOnly
{
    public function demoNoticeAction(): Action
    {
        return Action::make('demoNotice')
            ->modalIcon('heroicon-o-lock-closed')
            ->modalHeading('Saving is turned off in the live demo')
            ->modalDescription('This demo is open to everyone, so changes cannot be saved here. On a real website you would be able to edit and create items.')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Got it');
    }

    /** True (and shows the explanation box) when the demo does not allow the change. */
    protected function blockedInDemo(): bool
    {
        if (! DemoMode::enabled()) {
            return false;
        }

        $this->mountAction('demoNotice');

        return true;
    }
}
