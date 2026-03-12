<?php

namespace App\Ship\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * SafeContent Cast - Sanitizes HTML content to prevent XSS attacks.
 *
 * Uses HTMLPurifier via mews/purifier package to clean malicious HTML/JavaScript.
 * Apply this cast to any model attribute that stores HTML content from user input.
 *
 * @example
 * protected $casts = [
 *     'content' => SafeContent::class,
 *     'description' => SafeContent::class,
 * ];
 */
class SafeContent implements CastsAttributes
{
    /**
     * Cast the given value when retrieving from database.
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if (! $value) {
            return $value;
        }

        return html_entity_decode($this->clean($value));
    }

    /**
     * Prepare the given value for storage in database.
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $this->clean($value);
    }

    /**
     * Clean the content using HTMLPurifier.
     */
    protected function clean(mixed $value): mixed
    {
        if (! $value) {
            return $value;
        }

        if (config('purifier.enabled', true) === false) {
            return $value;
        }

        // Use 'default' config which allows basic HTML formatting
        return clean($value, 'default');
    }
}
