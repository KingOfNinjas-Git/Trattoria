<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DiningTable extends Model
{
    protected $fillable = ['number', 'token'];

    protected static function booted(): void
    {
        static::creating(function (DiningTable $table) {
            $table->token = $table->token ?: Str::random(12);
        });
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(TableSession::class);
    }

    public function url(): string
    {
        return route('table.menu', $this->token);
    }
}
