<?php

namespace App\Containers\AppSection\Media\Services;

use App\Containers\AppSection\Media\Supports\MediaSettingsStore;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Handles file validation and storage filename generation.
 *
 * Extracted from MediaService to isolate validation concerns.
 */
class MediaValidationService
{
    public function __construct(
        private readonly MediaSettingsStore $settingsStore,
    ) {
    }

    public function isAllowedFile(UploadedFile $file, ?User $user): bool
    {
        if (! $file->isValid()) {
            return false;
        }

        if ($user && $user->isSuperAdmin() && config('media.allowed_admin_to_upload_any_file_types', false)) {
            return true;
        }

        $extension = strtolower($file->getClientOriginalExtension());
        $allowed = $this->getAllowedExtensions();

        if (! in_array($extension, $allowed, true)) {
            return false;
        }

        return $this->isAllowedMimeType($file);
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedExtensions(): array
    {
        $allowed = config('media.allowed_mime_types', '');
        $allowed = array_filter(array_map('trim', explode(',', (string) $allowed)));

        return array_map('strtolower', $allowed);
    }

    /**
     * Generate a unique storage filename, ensuring no collision on disk.
     */
    public function createStorageFileName(string $name, string $extension, string $folderPath, string $disk): string
    {
        $settings = $this->settings();

        if ($settings->getBool('media_convert_file_name_to_uuid', (bool) config('media.convert_file_name_to_uuid', false))) {
            return Str::uuid() . '.' . $extension;
        }

        $useOriginal = $settings->getBool(
            'media_use_original_name_for_file_path',
            (bool) config('media.use_original_name_for_file_path', false)
        );
        $turnOffLatin = $settings->getBool(
            'media_turn_off_automatic_url_translation_into_latin',
            (bool) config('media.turn_off_automatic_url_translation_into_latin', false)
        );

        $base = $useOriginal
            ? $name
            : Str::slug($name, '-', $turnOffLatin ? null : 'en');

        $slug = $base === '' ? (string) time() : $base;
        $index = 1;
        $candidate = $slug;

        while (Storage::disk($disk)->exists(trim($folderPath . '/' . $candidate . '.' . $extension, '/'))) {
            $candidate = $slug . '-' . $index++;
        }

        return $candidate . '.' . $extension;
    }

    public function isAllowedMimeType(UploadedFile $file): bool
    {
        $mimeType = (string) ($file->getMimeType() ?: $file->getClientMimeType() ?: '');
        $mimeType = strtolower($mimeType);

        if ($mimeType === '' || $mimeType === 'application/octet-stream') {
            return true;
        }

        $allowed = $this->getAllowedMimeTypes();

        return in_array($mimeType, $allowed, true);
    }

    /**
     * @return array<int, string>
     */
    public function getAllowedMimeTypes(): array
    {
        $allowed = [];
        foreach ((array) config('media.mime_types', []) as $types) {
            if (! is_array($types)) {
                continue;
            }

            foreach ($types as $type) {
                $allowed[] = strtolower((string) $type);
            }
        }

        $allowed[] = 'image/jpg';

        return array_values(array_unique($allowed));
    }

    private function settings(): MediaSettingsStore
    {
        return $this->settingsStore;
    }
}
