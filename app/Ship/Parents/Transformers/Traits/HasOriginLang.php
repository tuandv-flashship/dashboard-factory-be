<?php

namespace App\Ship\Parents\Transformers\Traits;

use App\Containers\AppSection\Language\Supports\LanguageLocaleCache;

trait HasOriginLang
{
    /**
     * Get the origin language code for translatable content.
     * This indicates the language of the original/source content before translations.
     */
    protected function getOriginLang(): string
    {
        return LanguageLocaleCache::getDefaultLocaleCode()
            ?? config('app.locale', 'en');
    }
}
