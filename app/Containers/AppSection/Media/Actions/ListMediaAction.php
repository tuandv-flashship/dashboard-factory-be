<?php

namespace App\Containers\AppSection\Media\Actions;

use App\Containers\AppSection\Media\Models\MediaFile;
use App\Containers\AppSection\Media\Models\MediaFolder;
use App\Containers\AppSection\Media\Services\MediaService;
use App\Containers\AppSection\Media\UI\API\Transformers\MediaFileTransformer;
use App\Containers\AppSection\Media\UI\API\Transformers\MediaFolderTransformer;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Arr;

final class ListMediaAction extends ParentAction
{
    public function __construct(private readonly MediaService $mediaService)
    {
    }

    /**
     * @param array<string, mixed> $filters
     */
    public function run(array $filters, int $userId): array
    {
        $folderId = (int) ($filters['folder_id'] ?? 0);
        $viewIn = (string) ($filters['view_in'] ?? 'all_media');
        $search = trim((string) ($filters['search'] ?? ''));
        $filter = (string) ($filters['filter'] ?? 'everything');
        $sortBy = (string) ($filters['sort_by'] ?? 'name-asc');
        $perPage = max(1, (int) ($filters['limit'] ?? config('repository.pagination.limit', 10)));
        $page = max(1, (int) ($filters['page'] ?? 1));
        $includeSignedUrl = filter_var($filters['include_signed_url'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $folderQuery = MediaFolder::query();
        $fileQuery = MediaFile::query();

        if ($viewIn === 'trash') {
            $folderQuery->onlyTrashed();
            $fileQuery->onlyTrashed();
        }

        if ($search !== '') {
            $folderQuery->where('name', 'like', '%' . $search . '%');
            $fileQuery->where('name', 'like', '%' . $search . '%');
        }

        if ($viewIn === 'favorites' || $viewIn === 'recent') {
            $items = $viewIn === 'favorites'
                ? $this->mediaService->getFavoriteItems($userId)
                : $this->mediaService->getRecentItems($userId);

            $fileIds = collect($items)
                ->filter(fn (array $item) => ! filter_var($item['is_folder'] ?? false, FILTER_VALIDATE_BOOLEAN))
                ->pluck('id')
                ->all();

            $folderIds = collect($items)
                ->filter(fn (array $item) => filter_var($item['is_folder'] ?? false, FILTER_VALIDATE_BOOLEAN))
                ->pluck('id')
                ->all();

            if ($folderId > 0) {
                $folderQuery->where('parent_id', $folderId);
                $fileQuery->where('folder_id', $folderId);
            } else {
                $folderQuery->whereIn('id', $folderIds);
                $fileQuery->whereIn('id', $fileIds);
            }
        } else {
            $folderQuery->where('parent_id', $folderId);
            $fileQuery->where('folder_id', $folderId);
        }

        if ($filter !== 'everything') {
            $mimeTypes = Arr::get(config('media.mime_types', []), $filter);
            if (is_array($mimeTypes)) {
                $fileQuery->whereIn('mime_type', $mimeTypes);
            }
        }

        [$sortColumn, $sortDirection] = $this->parseSort($sortBy);

        $folderQuery->orderBy('name');
        $fileQuery->orderBy($sortColumn, $sortDirection);

        $files = $fileQuery->paginate($perPage, ['*'], 'page', $page);
        $folders = $folderQuery->get();

        $favoriteItems = collect($this->mediaService->getFavoriteItems($userId));
        $favoriteFileIds = $favoriteItems
            ->filter(fn (array $item) => ! filter_var($item['is_folder'] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $favoriteFolderIds = $favoriteItems
            ->filter(fn (array $item) => filter_var($item['is_folder'] ?? false, FILTER_VALIDATE_BOOLEAN))
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $fileTransformer = new MediaFileTransformer($this->mediaService, $includeSignedUrl, $favoriteFileIds);
        $folderTransformer = new MediaFolderTransformer($favoriteFolderIds);

        return [
            'files' => $files->getCollection()->map(fn (MediaFile $file) => $fileTransformer->transform($file))->values(),
            'folders' => $folders->map(fn (MediaFolder $folder) => $folderTransformer->transform($folder))->values(),
            'breadcrumbs' => $this->buildBreadcrumbs($folderId, $viewIn),
            'pagination' => [
                'total' => $files->total(),
                'per_page' => $files->perPage(),
                'current_page' => $files->currentPage(),
                'last_page' => $files->lastPage(),
            ],
            'selected_file_id' => $this->hashId($filters['selected_file_id'] ?? null),
        ];
    }

    private function parseSort(string $sortBy): array
    {
        $parts = explode('-', $sortBy);
        if (count($parts) !== 2) {
            return ['name', 'asc'];
        }

        $column = $parts[0];
        $direction = strtolower($parts[1]) === 'desc' ? 'desc' : 'asc';

        $allowed = ['name', 'created_at', 'size'];
        if (! in_array($column, $allowed, true)) {
            $column = 'name';
        }

        return [$column, $direction];
    }

    private function buildBreadcrumbs(int $folderId, string $viewIn): array
    {
        $breadcrumbs = [];

        $root = match ($viewIn) {
            'trash' => ['id' => 0, 'name' => 'Trash'],
            'recent' => ['id' => 0, 'name' => 'Recent'],
            'favorites' => ['id' => 0, 'name' => 'Favorites'],
            default => ['id' => 0, 'name' => 'All Media'],
        };

        $breadcrumbs[] = $root;

        if (! $folderId) {
            return $breadcrumbs;
        }

        $folder = MediaFolder::query()->withTrashed()->find($folderId);
        $path = [];

        while ($folder) {
            array_unshift($path, [
                'id' => $folder->getHashedKey(),
                'name' => $folder->name,
            ]);

            $folder = $folder->parent_id
                ? MediaFolder::query()->withTrashed()->find($folder->parent_id)
                : null;
        }

        return array_merge($breadcrumbs, $path);
    }


}
