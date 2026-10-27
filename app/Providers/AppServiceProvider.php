<?php

namespace App\Providers;

use App\Http\Middleware\SetLocale;
use App\Support\DemoMode;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Keep the customer's language on Livewire's AJAX updates too.
        Livewire::addPersistentMiddleware([SetLocale::class]);

        // Live demo: the admin panel is open to everyone, so nothing can be deleted. The delete buttons
        // still open a box that explains why, and even if the action were called directly it removes nothing.
        foreach ([DeleteAction::class, DeleteBulkAction::class] as $action) {
            $action::configureUsing(function (Action|BulkAction $action): void {
                if (! DemoMode::enabled()) {
                    return;
                }

                $action
                    ->requiresConfirmation()
                    ->modalIcon('heroicon-o-lock-closed')
                    ->modalHeading('Deleting is turned off in the live demo')
                    ->modalDescription('This demo is open to everyone, so items cannot be deleted here. On a real website you would be able to remove them.')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Got it')
                    ->action(fn () => null);
            });
        }
    }
}
