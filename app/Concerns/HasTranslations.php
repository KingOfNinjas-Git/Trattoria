<?php

namespace App\Concerns;

/**
 * Reads a field from the `translations` JSON column for the current locale,
 * falling back to the base (English) attribute.
 */
trait HasTranslations
{
    public function localized(string $field): ?string
    {
        $locale = app()->getLocale();

        return data_get($this->translations, "{$locale}.{$field}") ?: $this->getAttribute($field);
    }
}
