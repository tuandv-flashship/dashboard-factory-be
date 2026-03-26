<?php

namespace App\Containers\AppSection\Media\Tasks;

use App\Containers\AppSection\Media\Models\MediaFile;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class FindMediaFileByIndirectIdTask extends ParentTask
{
    public function run(string $hexId): MediaFile
    {
        return MediaFile::query()->findOrFail(hexdec($hexId));
    }
}

