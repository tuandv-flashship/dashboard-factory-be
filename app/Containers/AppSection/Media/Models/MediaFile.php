<?php

namespace App\Containers\AppSection\Media\Models;

use App\Containers\AppSection\Media\Supports\MediaRuntimeServices;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

final class MediaFile extends ParentModel
{
    use SoftDeletes;

    protected $table = 'media_files';

    protected $fillable = [
        'name',
        'mime_type',
        'size',
        'url',
        'options',
        'folder_id',
        'user_id',
        'alt',
        'visibility',
        'access_mode',
    ];

    protected $casts = [
        'options' => 'array',
    ];

    protected static function booted(): void
    {
        static::forceDeleted(function (MediaFile $file): void {
            MediaRuntimeServices::mediaService()->deleteFileFromStorage($file);
        });

        static::addGlobalScope('ownMedia', function ($query): void {
            $settings = MediaRuntimeServices::settingsStore();
            $onlyOwn = $settings->getBool(
                'user_can_only_view_own_media',
                (bool) config('media.only_view_own_media', false)
            );

            if (! $onlyOwn) {
                return;
            }

            $user = auth()->user();
            if (! $user || $user->isSuperAdmin()) {
                return;
            }

            $query->where('media_files.user_id', $user->getKey());
        });
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'folder_id')->withDefault();
    }

    protected function type(): Attribute
    {
        return Attribute::make(
            get: function ($value, $attributes) {
                $type = 'document';

                foreach (config('media.mime_types', []) as $key => $value) {
                    if (in_array($attributes['mime_type'], $value, true)) {
                        $type = $key;
                        break;
                    }
                }

                return $type;
            }
        );
    }

    protected function humanSize(): Attribute
    {
        return Attribute::get(fn () => $this->formatBytes($this->size));
    }

    protected function basename(): Attribute
    {
        return Attribute::get(fn () => File::basename($this->url));
    }

    protected function previewUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            $service = MediaRuntimeServices::mediaService();

            if ($this->visibility !== 'public') {
                return null;
            }

            if (Str::startsWith($this->mime_type, 'image/')) {
                return $service->url($this->url);
            }

            if (in_array($this->type, ['video', 'audio', 'text'], true)) {
                return $service->url($this->url);
            }

            if ($this->mime_type === 'application/pdf') {
                return $service->url($this->url);
            }

            $previewConfig = config('media.preview.document', []);
            if (! Arr::get($previewConfig, 'enabled')) {
                return null;
            }

            if (! in_array($this->mime_type, Arr::get($previewConfig, 'mime_types', []), true)) {
                return null;
            }

            $provider = Arr::get($previewConfig, 'default');
            $template = Arr::get($previewConfig, 'providers.' . $provider);
            if (! $template) {
                return null;
            }

            return Str::replace('{url}', urlencode($service->url($this->url)), $template);
        });
    }

    protected function previewType(): Attribute
    {
        return Attribute::get(function (): ?string {
            $previewConfig = config('media.preview.document', []);

            return Arr::get($previewConfig, 'type');
        });
    }

    protected function indirectUrl(): Attribute
    {
        return Attribute::get(function () {
            if (! $this->getKey()) {
                return null;
            }

            $id = dechex((int) $this->getKey());
            $hash = sha1($id);

            return route('media.indirect.url', compact('hash', 'id'));
        });
    }

    public function canGenerateThumbnails(): bool
    {
        $settings = MediaRuntimeServices::settingsStore();

        if (! $settings->getBool('media_enable_thumbnail_sizes', (bool) config('media.enable_thumbnail_sizes', true))) {
            return false;
        }

        if (! config('media.generate_thumbnails_enabled', false)) {
            return false;
        }

        return Str::startsWith($this->mime_type, 'image/');
    }

    public static function createName(string $name, int|string|null $folderId): string
    {
        $newName = $name;
        $index = 1;
        $baseName = $newName;

        while (self::query()->where('name', $newName)->where('folder_id', $folderId)->withTrashed()->exists()) {
            $newName = $baseName . '-' . $index++;
        }

        return $newName;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);

        $value = $bytes / (1024 ** $power);

        return number_format($value, 2) . ' ' . $units[$power];
    }

}
