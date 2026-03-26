<?php

namespace App\Containers\AppSection\Media\Actions;

use App\Containers\AppSection\Media\Tasks\BuildShowMediaFileResponseTask;
use App\Containers\AppSection\Media\Tasks\FindMediaFileByIndirectIdTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ShowMediaFileAction extends ParentAction
{
    public function __construct(
        private readonly FindMediaFileByIndirectIdTask $findMediaFileByIndirectIdTask,
        private readonly BuildShowMediaFileResponseTask $buildShowMediaFileResponseTask,
    ) {
    }

    public function run(
        string $hash,
        string $id,
        bool $hasValidSignature,
        bool $isAuthenticated,
    ): Response|BinaryFileResponse|RedirectResponse {
        if (sha1($id) !== $hash) {
            abort(404);
        }

        $file = $this->findMediaFileByIndirectIdTask->run($id);

        return $this->buildShowMediaFileResponseTask->run(
            $file,
            $hasValidSignature,
            $isAuthenticated,
        );
    }
}

