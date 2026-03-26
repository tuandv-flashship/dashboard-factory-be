<?php

namespace App\Containers\AppSection\Media\Tasks;

use App\Containers\AppSection\Media\Models\MediaFile;
use App\Containers\AppSection\Media\Services\MediaService;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class BuildShowMediaFileResponseTask extends ParentTask
{
    public function __construct(
        private readonly MediaService $mediaService,
    ) {
    }

    public function run(
        MediaFile $file,
        bool $hasValidSignature,
        bool $isAuthenticated,
    ): Response|BinaryFileResponse|RedirectResponse {
        $disk = $file->visibility === 'private'
            ? $this->mediaService->getPrivateDisk()
            : $this->mediaService->getDisk();

        if ($file->visibility === 'public') {
            return redirect()->to($this->mediaService->url($file->url));
        }

        $accessMode = $this->mediaService->resolveAccessModeForFile($file);
        if ($accessMode === 'signed') {
            if (! $hasValidSignature) {
                abort(403);
            }
        } elseif (! $isAuthenticated) {
            abort(403);
        }

        return response(Storage::disk($disk)->get($file->url), 200, [
            'Content-Type' => $file->mime_type,
        ]);
    }
}

