<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUSES = ['received', 'preparing', 'ready', 'served'];

    public const CANCELLED = 'cancelled';

    /** Minutes after which an order that has not reached "ready" is flagged as late. */
    public const LATE_AFTER_MINUTES = 15;

    /** Timestamp column that records when each status was reached. */
    private const STAMPS = ['preparing' => 'preparing_at', 'ready' => 'ready_at', 'served' => 'served_at'];

    protected $fillable = [
        'dining_table_id', 'table_session_id', 'status', 'notes', 'total_cents',
        'preparing_at', 'ready_at', 'served_at', 'cancelled_at',
    ];

    protected $casts = [
        'preparing_at' => 'datetime',
        'ready_at' => 'datetime',
        'served_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /** Orders that count towards the bill and the sales figures. */
    public function scopeNotCancelled(Builder $query): Builder
    {
        return $query->where('status', '!=', self::CANCELLED);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::CANCELLED;
    }

    public function getTotalAttribute(): string
    {
        return number_format($this->total_cents / 100, 2, ',', '.');
    }

    /** Move one stage forward: received, preparing, ready, served. */
    public function advance(): bool
    {
        $next = self::STATUSES[array_search($this->status, self::STATUSES, true) + 1] ?? null;
        if ($this->isCancelled() || ! $next) {
            return false;
        }

        $this->status = $next;
        $this->{self::STAMPS[$next]} = now();

        return $this->save();
    }

    /** Undo the last stage (a mis-tap). Clears the timestamp that stage had set. */
    public function retreat(): bool
    {
        $index = array_search($this->status, self::STATUSES, true);
        if ($this->isCancelled() || $index === false || $index === 0) {
            return false;
        }

        $this->{self::STAMPS[$this->status]} = null;
        $this->status = self::STATUSES[$index - 1];

        return $this->save();
    }

    public function cancel(): bool
    {
        if ($this->isCancelled() || $this->status === 'served') {
            return false;
        }

        $this->status = self::CANCELLED;
        $this->cancelled_at = now();

        return $this->save();
    }

    public function readyItemsCount(): int
    {
        return $this->items->where('is_ready', true)->count();
    }

    /**
     * How long a guest should expect to wait, based on how long recent orders really took
     * (order placed until ready), clamped to a sensible range. Falls back to 15 minutes.
     */
    public static function estimatedWaitMinutes(): int
    {
        $recent = static::whereNotNull('ready_at')
            ->where('created_at', '>=', now()->subDays(7))
            ->latest('id')
            ->limit(30)
            ->get(['created_at', 'ready_at']);

        if ($recent->count() < 3) {
            return 15;
        }

        $average = $recent->avg(fn (Order $o) => $o->created_at->diffInSeconds($o->ready_at) / 60);

        return (int) max(5, min(45, round($average)));
    }
}
