<?php

namespace App\Containers\AppSection\Setting\Actions;

use App\Containers\AppSection\Media\Supports\MediaSettingsStore;
use App\Containers\AppSection\Setting\Tasks\UpsertSettingsTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class UpdateMediaSettingsAction extends ParentAction
{
    public function __construct(
        private readonly UpsertSettingsTask $upsertSettingsTask,
        private readonly MediaSettingsStore $settingsStore,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function run(array $payload): array
    {
        $data = $this->normalizePayload($payload);

        $this->upsertSettingsTask->run($data);
        $this->settingsStore->clear();

        return $this->settingsStore->all();
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        foreach ($this->secretKeys() as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] === '********') {
                unset($payload[$key]);
            }
        }

        if (isset($payload['media_folders_can_add_watermark']) && is_array($payload['media_folders_can_add_watermark'])) {
            $payload['media_folders_can_add_watermark'] = json_encode(
                $payload['media_folders_can_add_watermark'],
                JSON_THROW_ON_ERROR
            );
        }

        if (isset($payload['media_sizes']) && is_array($payload['media_sizes'])) {
            foreach ($payload['media_sizes'] as $name => $values) {
                if (! is_array($values)) {
                    continue;
                }

                $width = isset($values['width']) ? (int) $values['width'] : null;
                $height = isset($values['height']) ? (int) $values['height'] : null;

                if ($width !== null) {
                    $payload[sprintf('media_sizes_%s_width', $name)] = $width;
                }

                if ($height !== null) {
                    $payload[sprintf('media_sizes_%s_height', $name)] = $height;
                }
            }

            unset($payload['media_sizes']);
        }

        return $payload;
    }

    /**
     * @return array<int, string>
     */
    private function secretKeys(): array
    {
        return [
            'media_aws_secret_key',
            'media_r2_secret_key',
            'media_do_spaces_secret_key',
            'media_wasabi_secret_key',
            'media_bunnycdn_key',
            'media_backblaze_secret_key',
        ];
    }
}
