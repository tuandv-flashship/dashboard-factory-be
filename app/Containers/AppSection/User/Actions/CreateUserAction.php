<?php

namespace App\Containers\AppSection\User\Actions;

use App\Containers\AppSection\AuditLog\Supports\AuditLogRecorder;
use App\Containers\AppSection\User\Models\User;
use App\Containers\AppSection\User\Tasks\CreateUserTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Facades\DB;

final class CreateUserAction extends ParentAction
{
    public function __construct(
        private readonly CreateUserTask $createUserTask,
    ) {
    }

    public function run(array $data, int ...$roleIds): User
    {
        return DB::transaction(function () use ($data, $roleIds): User {
            $user = $this->createUserTask->run($data);

            if ($roleIds !== []) {
                $user->assignRole($roleIds);
            }

            $user->load(['avatar', 'roles']);

            AuditLogRecorder::recordModel('created', $user);

            return $user;
        });
    }
}
