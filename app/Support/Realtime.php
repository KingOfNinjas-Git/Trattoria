<?php

namespace App\Support;

use App\Events\MenuUpdated;
use App\Events\TableUpdated;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Fire-and-forget broadcasting. If the Reverb server is not running the app keeps working
 * (screens fall back to slow polling), so a failed broadcast must never break a request.
 */
class Realtime
{
    /** $origin identifies the page that caused the change, so that page can skip refreshing itself. */
    public static function table(string $sessionToken, string $kind, ?string $origin = null): void
    {
        self::safely(fn () => TableUpdated::dispatch($sessionToken, $kind, $origin));
    }

    public static function menu(): void
    {
        self::safely(fn () => MenuUpdated::dispatch());
    }

    private static function safely(callable $fn): void
    {
        try {
            $fn();
        } catch (Throwable $e) {
            Log::debug('Broadcast skipped: '.$e->getMessage());
        }
    }
}
