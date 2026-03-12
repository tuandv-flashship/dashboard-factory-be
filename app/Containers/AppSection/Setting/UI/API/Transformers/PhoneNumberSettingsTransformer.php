<?php

namespace App\Containers\AppSection\Setting\UI\API\Transformers;

use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class PhoneNumberSettingsTransformer extends ParentTransformer
{
    /**
     * @param array<string, mixed> $settings
     */
    public function transform(mixed $settings): array
    {
        if (is_object($settings)) {
            $settings = get_object_vars($settings);
        }

        if (!is_array($settings)) {
            $settings = [];
        }

        $availableCountries = $settings['phone_number_available_countries'] ?? [];

        if (is_string($availableCountries)) {
            $decoded = json_decode($availableCountries, true);
            $availableCountries = is_array($decoded) ? $decoded : [];
        }

        return [
            'type' => 'PhoneNumberSettings',
            'id' => 'phone-number',
            'phone_number_enable_country_code' => (bool) ($settings['phone_number_enable_country_code'] ?? false),
            'phone_number_available_countries' => $availableCountries,
            'phone_number_min_length' => isset($settings['phone_number_min_length'])
                ? (int) $settings['phone_number_min_length']
                : null,
            'phone_number_max_length' => isset($settings['phone_number_max_length'])
                ? (int) $settings['phone_number_max_length']
                : null,
        ];
    }
}
