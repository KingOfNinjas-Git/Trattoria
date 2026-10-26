<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceRequest extends Model
{
    public const WAITER = 'waiter';

    public const BILL = 'bill';

    protected $fillable = ['dining_table_id', 'table_session_id', 'type', 'acknowledged_at', 'resolved_at'];

    protected $casts = ['acknowledged_at' => 'datetime', 'resolved_at' => 'datetime'];

    public function table(): BelongsTo
    {
        return $this->belongsTo(DiningTable::class, 'dining_table_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(TableSession::class, 'table_session_id');
    }
}
