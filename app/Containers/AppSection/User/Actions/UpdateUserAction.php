<?php

namespace App\Containers\AppSection\User\Actions;

use App\Containers\AppSection\AuditLog\Supports\AuditLogRecorder;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\User\Tasks\UpdateUserTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class UpdateUserAction extends ParentAction
{
    public function __construct(
        private readonly UpdateUserTask $updateUserTask,
    ) {
    }

    public function run(int $id, array $data): User
    {
        $user = $this->updateUserTask->run($id, $data);

        AuditLogRecorder::recordModel('updated', $user);

        return $user;
    }
}
