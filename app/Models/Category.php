<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Category extends Model
{
    use HasTranslations;

    protected $fillable = ['name', 'subtitle', 'translations', 'sort_order', 'available_from', 'available_until'];

    protected $casts = ['translations' => 'array'];

    /** Whether the section can be ordered from right now (always true without a schedule). */
    public function isOpenNow(?Carbon $at = null): bool
    {
        if (! $this->available_from || ! $this->available_until) {
            return true;
        }

        $at ??= now();
        $time = $at->format('H:i:s');
        $from = substr($this->available_from, 0, 8);
        $until = substr($this->available_until, 0, 8);

        // A window like 18:00 to 02:00 runs past midnight.
        return $from <= $until
            ? ($time >= $from && $time < $until)
            : ($time >= $from || $time < $until);
    }

    public function scheduleLabel(): ?string
    {
        return $this->available_from && $this->available_until
            ? substr($this->available_from, 0, 5).' - '.substr($this->available_until, 0, 5)
            : null;
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order');
    }
}
