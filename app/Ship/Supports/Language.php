<?php

namespace App\Ship\Supports;

use Illuminate\Support\Facades\App;

class Language
{
    /**
     * Get all country flag codes.
     *
     * @return array<string, string>
     */
    public static function getListLanguageFlags(): array
    {
        return config('languages.flags', []);
    }

    /**
     * Get all available locales with structured metadata.
     *
     * @return array<string, array{locale: string, code: string, name: string, flag: string, is_rtl: bool}>
     */
    public static function getAvailableLocales(bool $original = false): array
    {
        $locales = config('languages.locales', []);

        return array_map(static function (array $language): array {
            return [
                'locale' => $language['locale'],
                'code' => $language['code'],
                'name' => $language['name'],
                'flag' => $language['flag'] ?? $language['locale'],
                'is_rtl' => ($language['dir'] ?? 'ltr') === 'rtl',
            ];
        }, $locales);
    }

    /**
     * Get raw language list.
     *
     * @return array<string, array{locale: string, code: string, name: string, dir: string, flag: string}>
     */
    public static function getListLanguages(): array
    {
        return config('languages.locales', []);
    }

    /**
     * Get the default language metadata based on app locale config.
     *
     * @return array{locale: string, code: string, name: string, flag: string, is_rtl: bool}
     */
    public static function getDefaultLanguage(): array
    {
        $available = static::getAvailableLocales(true);

        $preferredLocales = [
            config('app.locale', 'en'),
            config('app.fallback_locale', 'en'),
        ];

        foreach ($preferredLocales as $locale) {
            if (! $locale) {
                continue;
            }

            $normalized = str_replace('-', '_', $locale);

            if (isset($available[$locale])) {
                return $available[$locale];
            }

            if (isset($available[$normalized])) {
                return $available[$normalized];
            }

            foreach ($available as $language) {
                if (($language['locale'] ?? null) === $locale || ($language['locale'] ?? null) === $normalized) {
                    return $language;
                }
            }
        }

        if (isset($available['en_US'])) {
            return $available['en_US'];
        }

        if (! empty($available)) {
            return reset($available);
        }

        return [
            'locale' => 'en',
            'code' => 'en_US',
            'name' => 'English',
            'flag' => 'us',
            'is_rtl' => false,
        ];
    }

    /**
     * Get unique locale → name mapping.
     *
     * @return array<string, string>
     */
    public static function getLocales(): array
    {
        $locales = [];

        foreach (static::getListLanguages() as $language) {
            $locale = $language['locale'] ?? null;
            $name = $language['name'] ?? null;

            if (! $locale || ! $name) {
                continue;
            }

            if (! array_key_exists($locale, $locales)) {
                $locales[$locale] = $name;
            }
        }

        $locales = [
            ...$locales,
            'de_CH' => 'Deutsch (Schweiz)',
            'pt_BR' => 'Português (Brasil)',
            'sr_Cyrl' => 'Српски (ћирилица)',
            'sr_Latn' => 'Srpski (latinica)',
            'sr_Latn_ME' => 'Srpski (latinica, Crna Gora)',
            'uz_Cyrl' => 'Ўзбек (Ўзбекистон)',
            'uz_Latn' => "O\u{2018}zbek",
            'zh_CN' => '中文 (中国)',
            'zh_TW' => '中文 (台灣)',
            'zh_HK' => '中文 (香港)',
        ];

        ksort($locales);

        return $locales;
    }

    /**
     * Get unique locale keys.
     *
     * @return string[]
     */
    public static function getLocaleKeys(): array
    {
        return array_unique(array_keys(static::getLocales()));
    }

    /**
     * Get all language codes (e.g. 'en_US', 'vi', 'zh_CN').
     *
     * @return string[]
     */
    public static function getLanguageCodes(): array
    {
        $codes = [];

        foreach (static::getListLanguages() as $language) {
            $code = $language['code'] ?? null;

            if ($code) {
                $codes[] = $code;
            }
        }

        return array_values(array_unique($codes));
    }

    /**
     * Get the current locale metadata.
     *
     * @return array{locale: string, code: string, name: string, flag: string, is_rtl: bool}
     */
    public static function getCurrentLocale(): array
    {
        $available = static::getAvailableLocales(true);
        $currentLocale = App::getLocale();

        if (isset($available[$currentLocale])) {
            return $available[$currentLocale];
        }

        $normalized = str_replace('-', '_', $currentLocale);

        if (isset($available[$normalized])) {
            return $available[$normalized];
        }

        foreach ($available as $language) {
            if (($language['locale'] ?? null) === $currentLocale || ($language['locale'] ?? null) === $normalized) {
                return $language;
            }
        }

        return static::getDefaultLanguage();
    }
}
