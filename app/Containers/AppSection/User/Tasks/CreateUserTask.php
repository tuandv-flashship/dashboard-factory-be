<?php

namespace App\Containers\AppSection\User\Tasks;

use App\Containers\AppSection\User\Data\Repositories\UserRepository;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class CreateUserTask extends ParentTask
{
    public function __construct(
        private readonly UserRepository $repository,
    ) {
    }

    public function run(array $data): User
    {
        $validationData = ['email' => strtolower($data['email'])];
        $validationRules = ['email' => ['unique:users,email']];

        if (!empty($data['username'])) {
            $validationData['username'] = strtolower($data['username']);
            $validationRules['username'] = ['unique:users,username'];
        }

        tap(validator($validationData, $validationRules))->validate();

        return $this->repository->create($data);
    }
}
