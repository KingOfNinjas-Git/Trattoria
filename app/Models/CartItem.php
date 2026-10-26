<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    protected $fillable = ['table_session_id', 'menu_item_id', 'selection', 'selection_key', 'quantity'];

    protected $casts = ['selection' => 'array'];

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function unitPriceCents(): int
    {
        return $this->menuItem->unitPriceCents($this->selection ?? []);
    }

    public function lineTotalCents(): int
    {
        return $this->unitPriceCents() * $this->quantity;
    }

    public function optionsText(): ?string
    {
        return $this->menuItem->optionsText($this->selection ?? []);
    }
}
