<?php

namespace App\Support;

use App\Models\CartItem;
use App\Models\Order;
use App\Models\ServiceRequest;
use App\Models\TableSession;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/** Everything that only exists for the public portfolio demo (see config/trattoria.php). */
class DemoMode
{
    public static function enabled(): bool
    {
        return (bool) config('trattoria.demo');
    }

    public static function isDemoUser(?User $user): bool
    {
        return $user !== null && self::enabled() && $user->email === config('trattoria.demo_email');
    }

    /** The staff account behind the "Try the kitchen board" button: no known password, no admin panel. */
    public static function staffUser(): User
    {
        return User::firstOrCreate(
            ['email' => config('trattoria.demo_email')],
            ['name' => 'Demo staff', 'password' => Str::random(40)],
        );
    }

    /** Removes visitors' leftovers older than the keep time. Runs at most once an hour. */
    public static function cleanUp(): void
    {
        if (! self::enabled() || ! Cache::add('demo-cleanup', 1, 3600)) {
            return;
        }

        $before = now()->subHours(config('trattoria.demo_keep_hours'));

        Order::where('created_at', '<', $before)->delete();          // its dishes go with it
        ServiceRequest::where('created_at', '<', $before)->delete();
        CartItem::where('created_at', '<', $before)->delete();
        TableSession::where('created_at', '<', $before)->delete();
    }
}
