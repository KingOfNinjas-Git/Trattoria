<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    protected $fillable = [
        'table_session_id', 'amount_cents', 'tip_cents', 'kind', 'label', 'card_brand', 'card_last4', 'reference', 'paid_at',
    ];

    protected $casts = ['paid_at' => 'datetime'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getTotalCentsAttribute(): int
    {
        return $this->amount_cents + $this->tip_cents;
    }
}
