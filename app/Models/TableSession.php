<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * One "sitting" at a table. Everybody who scans the table's QR code while it is open
 * shares the same cart, orders and bill. Staff close it when the table pays.
 */
class TableSession extends Model
{
    public const IDLE_HOURS = 8;

    protected $fillable = ['dining_table_id', 'token', 'last_activity_at', 'closed_at'];

    protected $casts = [
        'last_activity_at' => 'datetime',
        'closed_at' => 'datetime',
    ];

    public static function forTable(DiningTable $table): self
    {
        $session = static::where('dining_table_id', $table->id)->whereNull('closed_at')->latest('id')->first();

        if ($session && $session->last_activity_at?->lt(now()->subHours(self::IDLE_HOURS))) {
            $session->close();
            $session = null;
        }

        return $session ?? static::create([
            'dining_table_id' => $table->id,
            'token' => Str::random(24),
            'last_activity_at' => now(),
        ]);
    }

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function touchActivity(): void
    {
        $this->forceFill(['last_activity_at' => now()])->save();
    }

    public function close(): void
    {
        $this->forceFill(['closed_at' => now()])->save();
        $this->serviceRequests()->whereNull('resolved_at')->update(['resolved_at' => now()]);
        $this->cartItems()->delete();
    }

    public function totalCents(): int
    {
        return (int) $this->orders()->notCancelled()->sum('total_cents');
    }
}
