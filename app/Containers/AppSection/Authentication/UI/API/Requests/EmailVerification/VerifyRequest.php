<?php

namespace App\Containers\AppSection\Authentication\UI\API\Requests\EmailVerification;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class VerifyRequest extends ParentRequest
{
    protected array $decode = [
        'id',
    ];
    
    public function rules(): array
    {
        return [];
    }

    public function authorize(): bool
    {
        $user = $this->authenticatedUser();
        $userId = (string) $user->getKey();
        $routeId = (string) $this->route('id');
        $emailHash = sha1($user->getEmailForVerification());
        $routeHash = (string) $this->route('hash');

        return hash_equals($userId, $routeId) && hash_equals($emailHash, $routeHash);
    }
}
