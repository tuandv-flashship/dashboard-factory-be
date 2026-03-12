<?php

namespace App\Containers\AppSection\User\Actions;

use App\Containers\AppSection\AuditLog\Supports\AuditLogRecorder;
use App\Containers\AppSection\Media\Models\MediaFolder;
use App\Containers\AppSection\Media\Services\MediaService;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\User\Tasks\UpdateUserTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Http\UploadedFile;

final class UpdateUserAvatarAction extends ParentAction
{
    private const AVATAR_FOLDER_NAME = 'users';

    public function __construct(
        private readonly MediaService $mediaService,
        private readonly UpdateUserTask $updateUserTask,
    ) {
    }

    public function run(int $userId, UploadedFile $file): User
    {
        $folder = MediaFolder::query()
            ->where('name', self::AVATAR_FOLDER_NAME)
            ->where('parent_id', 0)
            ->first();

        if (!$folder) {
            $folder = MediaFolder::query()->create([
                'name' => self::AVATAR_FOLDER_NAME,
                'slug' => self::AVATAR_FOLDER_NAME,
                'parent_id' => 0,
                'user_id' => 0,
            ]);
        }

        $mediaFile = $this->mediaService->storeUploadedFile(
            $file,
            (int) $folder->getKey(),
            $userId,
        );

        $user = $this->updateUserTask->run($userId, [
            'avatar_id' => $mediaFile->getKey(),
        ]);

        $user->setRelation('avatar', $mediaFile);

        AuditLogRecorder::recordModel('updated', $user);

        return $user;
    }
}
