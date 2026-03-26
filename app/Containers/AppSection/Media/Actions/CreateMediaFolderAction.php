<?php

namespace App\Containers\AppSection\Media\Actions;

use App\Containers\AppSection\AuditLog\Supports\AuditLogRecorder;
use App\Containers\AppSection\Media\Models\MediaFolder;
use App\Ship\Parents\Actions\Action as ParentAction;

final class CreateMediaFolderAction extends ParentAction
{
    public function run(string $name, int $parentId, int $userId, ?string $color = null): MediaFolder
    {
        $folder = MediaFolder::query()->create([
            'name' => MediaFolder::createName($name, $parentId),
            'slug' => MediaFolder::createSlug($name, $parentId),
            'parent_id' => $parentId,
            'user_id' => $userId,
            'color' => $color,
        ]);

        AuditLogRecorder::recordModel('created', $folder);

        return $folder;
    }
}
