<?php

namespace App\Containers\AppSection\Authentication\Actions\EmailVerification;

use App\Containers\AppSection\AuditLog\Supports\AuditLogRecorder;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\MustVerifyEmail;

final class VerifyAction extends ParentAction
{
    public function run(MustVerifyEmail $user): void
    {
        if (!$user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            AuditLogRecorder::record(
                module: 'authentication',
                action: 'verified email',
                referenceId: (string) $user->getAuthIdentifier(),
                referenceName: (string) ($user->name ?? ''),
                type: 'info',
            );

            event(new Verified($user));
        }
    }
}
