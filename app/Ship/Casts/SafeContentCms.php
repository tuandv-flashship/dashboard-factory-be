<?php

namespace App\Ship\Casts;

use Illuminate\Database\Eloquent\Model;

/**
 * SafeContentCms Cast - For rich HTML content fields (posts, pages content).
 *
 * Uses 'cms' purifier config which allows more HTML elements for rich content.
 */
class SafeContentCms extends SafeContent
{
    protected function clean(mixed $value): mixed
    {
        if (! $value) {
            return $value;
        }

        if (config('purifier.enabled', true) === false) {
            return $value;
        }

        // Use 'cms' config which allows rich HTML content
        return clean($value, 'cms');
    }
}
