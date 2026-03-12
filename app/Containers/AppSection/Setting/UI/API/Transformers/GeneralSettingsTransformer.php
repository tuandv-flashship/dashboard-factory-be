<?php

namespace App\Containers\AppSection\Setting\UI\API\Transformers;

use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class GeneralSettingsTransformer extends ParentTransformer
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

        $adminEmails = $settings['admin_email'] ?? [];

        if (is_string($adminEmails)) {
            $decoded = json_decode($adminEmails, true);
            $adminEmails = is_array($decoded) ? $decoded : [];
        }

        return [
            'type' => 'GeneralSettings',
            'id' => 'general',
            'admin_email' => $adminEmails,
            'time_zone' => $settings['time_zone'] ?? 'UTC',
            'enable_send_error_reporting_via_email' => (bool) ($settings['enable_send_error_reporting_via_email'] ?? false),
            'locale_direction' => $settings['locale_direction'] ?? 'ltr',
            'locale' => $settings['locale'] ?? null,
            'redirect_404_to_homepage' => (bool) ($settings['redirect_404_to_homepage'] ?? false),
            'request_log_data_retention_period' => isset($settings['request_log_data_retention_period'])
                ? (int) $settings['request_log_data_retention_period']
                : null,
            'audit_log_data_retention_period' => isset($settings['audit_log_data_retention_period'])
                ? (int) $settings['audit_log_data_retention_period']
                : null,
        ];
    }
}
