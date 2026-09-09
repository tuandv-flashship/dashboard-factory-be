<?php

namespace App\Containers\AppSection\User\Actions;

use App\Containers\AppSection\AuditLog\Supports\AuditLogRecorder;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\User\Tasks\UpdateUserTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class UpdateUserAction extends ParentAction
{
    public function __construct(
        private readonly UpdateUserTask $updateUserTask,
    ) {
    }

    /**
     * @param int[]|null $roleIds null leaves the current roles untouched,
     *                            an array replaces them with exactly that set
     */
    public function run(int $id, array $data, array|null $roleIds = null): User
    {
        return DB::transaction(function () use ($id, $data, $roleIds): User {
            $user = $this->updateUserTask->run($id, $data);

            if (!is_null($roleIds)) {
                $user->syncRoles($roleIds);
                $user->load('roles');
            }

            AuditLogRecorder::recordModel('updated', $user);

            return $user;
        });
    }
}
