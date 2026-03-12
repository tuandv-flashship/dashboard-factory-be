<?php

namespace App\Containers\AppSection\Authentication\Actions\Api\WebClient;

use App\Containers\AppSection\AuditLog\Supports\AuditLogRecorder;
use App\Containers\AppSection\Authentication\Data\DTOs\PasswordToken;
use App\Containers\AppSection\Authentication\Data\Factories\PasswordTokenFactory;
use App\Containers\AppSection\Authentication\Values\Clients\WebClient;
use App\Containers\AppSection\Authentication\Values\RequestProxies\PasswordGrant\AccessTokenProxy;
use App\Containers\AppSection\Authentication\Values\UserCredential;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Actions\Action as ParentAction;

final class IssueTokenAction extends ParentAction
{
    public function __construct(
        private readonly PasswordTokenFactory $factory,
    ) {
    }

    public function run(UserCredential $credential): PasswordToken
    {
        $token = $this->factory->make(
            AccessTokenProxy::create(
                $credential,
                WebClient::create(),
            ),
        );

        $user = User::query()
            ->where('email', strtolower($credential->username()))
            ->first();

        if ($user) {
            AuditLogRecorder::record(
                module: 'authentication',
                action: 'logged in',
                referenceId: (string) $user->getKey(),
                referenceName: (string) $user->name,
                type: 'info',
            );
        }

        return $token;
    }
}
