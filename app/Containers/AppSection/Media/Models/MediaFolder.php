<?php

namespace App\Containers\AppSection\Media\Models;

use App\Containers\AppSection\Media\Supports\MediaRuntimeServices;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class MediaFolder extends ParentModel
{
    use SoftDeletes;

    protected $table = 'media_folders';

    protected $fillable = [
        'name',
        'slug',
        'parent_id',
        'user_id',
        'color',
    ];

    protected static function booted(): void
    {
        static::deleted(function (MediaFolder $folder): void {
            if ($folder->isForceDeleting()) {
                $folder->files()->withTrashed()->each(fn (MediaFile $file) => $file->forceDelete());

                $service = MediaRuntimeServices::mediaService();
                $path = $service->getFolderPath($folder->getKey());
                if ($path && Storage::disk($service->getDisk())->directoryExists($path)) {
                    Storage::disk($service->getDisk())->deleteDirectory($path);
                }
            } else {
                $folder->files()->withTrashed()->each(fn (MediaFile $file) => $file->delete());
            }
        });

        static::restoring(function (MediaFolder $folder): void {
            $folder->files()->each(fn (MediaFile $file) => $file->restore());
        });

        static::addGlobalScope('ownMedia', function ($query): void {
            if (! config('media.only_view_own_media', false)) {
                return;
            }

            $user = auth()->user();
            if (! $user || $user->isSuperAdmin()) {
                return;
            }

            $query->where('media_folders.user_id', $user->getKey());
        });
    }

    public function files(): HasMany
    {
        return $this->hasMany(MediaFile::class, 'folder_id', 'id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(MediaFolder::class, 'parent_id')->withDefault();
    }

    public static function getFullPath(int|string|null $folderId, ?string $path = ''): ?string
    {
        if (! $folderId) {
            return $path;
        }

        $folder = self::query()->withTrashed()->find($folderId);

        if (! $folder) {
            return $path;
        }

        $parent = self::getFullPath($folder->parent_id, $path);

        if (! $parent) {
            return $folder->slug;
        }

        return rtrim($parent, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $folder->slug;
    }

    public static function createSlug(string $name, int|string|null $parentId): string
    {
        $slug = Str::slug($name);
        $index = 1;
        $baseSlug = $slug;

        while (self::query()->where('slug', $slug)->where('parent_id', $parentId)->withTrashed()->exists()) {
            $slug = $baseSlug . '-' . $index++;
        }

        return $slug;
    }

    public static function createName(string $name, int|string|null $parentId): string
    {
        $newName = $name;
        $index = 1;
        $baseName = $newName;

        while (self::query()->where('name', $newName)->where('parent_id', $parentId)->withTrashed()->exists()) {
            $newName = $baseName . '-' . $index++;
        }

        return $newName;
    }

}
