<?php

namespace App\Models;

use App\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    use HasTranslations;

    public const ALLERGENS = [
        'gluten' => 'Gluten',
        'dairy' => 'Dairy',
        'eggs' => 'Eggs',
        'nuts' => 'Nuts',
        'fish' => 'Fish',
        'shellfish' => 'Shellfish',
        'soy' => 'Soy',
        'celery' => 'Celery',
        'sulphites' => 'Sulphites',
    ];

    protected $fillable = [
        'category_id', 'name', 'description', 'translations', 'price_cents', 'image',
        'is_vegetarian', 'is_spicy', 'is_available', 'allergens', 'options', 'sort_order',
    ];

    protected $casts = [
        'translations' => 'array',
        'allergens' => 'array',
        'options' => 'array',
        'is_vegetarian' => 'boolean',
        'is_spicy' => 'boolean',
        'is_available' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('images/menu/'.$this->image) : null;
    }

    /** A small copy of the photo for lists and the cart (falls back to the full picture when there is none). */
    public function getThumbUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        $thumb = preg_replace('/\.webp$/', '-sm.webp', $this->image);

        return is_file(public_path('images/menu/'.$thumb)) ? asset('images/menu/'.$thumb) : $this->image_url;
    }

    public function getPriceAttribute(): string
    {
        return number_format($this->price_cents / 100, 2, ',', '.');
    }

    /** Can be ordered right now: switched on by the kitchen and its menu section is open. */
    public function isOrderable(): bool
    {
        return $this->is_available && $this->category->isOpenNow();
    }

    public function hasOptions(): bool
    {
        return ! empty($this->options);
    }

    /**
     * Validate a customer's selection ([groupIndex => [choiceIndex, ...]]) against this
     * item's option groups. Returns the cleaned selection, or null if it is invalid
     * (too many choices for a single group, or a required group left empty).
     */
    public function normaliseSelection(array $selection): ?array
    {
        $clean = [];

        foreach ($this->options ?? [] as $g => $group) {
            $picked = collect($selection[$g] ?? [])
                ->map(fn ($c) => (int) $c)
                ->unique()
                ->filter(fn ($c) => isset($group['choices'][$c]))
                ->sort()
                ->values()
                ->all();

            if (($group['type'] ?? 'single') === 'single' && count($picked) > 1) {
                return null;
            }
            if (! empty($group['required']) && count($picked) === 0) {
                return null;
            }
            if ($picked) {
                $clean[$g] = $picked;
            }
        }

        return $clean;
    }

    public function unitPriceCents(array $selection): int
    {
        $price = $this->price_cents;
        foreach ($selection as $g => $choices) {
            foreach ($choices as $c) {
                $price += (int) ($this->options[$g]['choices'][$c]['price_cents'] ?? 0);
            }
        }

        return $price;
    }

    public function optionsText(array $selection): ?string
    {
        $names = [];
        foreach ($selection as $g => $choices) {
            foreach ($choices as $c) {
                $names[] = $this->options[$g]['choices'][$c]['name'] ?? null;
            }
        }

        return $names ? implode(', ', array_filter($names)) : null;
    }
}
