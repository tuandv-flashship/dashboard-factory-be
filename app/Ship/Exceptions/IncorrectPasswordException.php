<?php

namespace App\Ship\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class IncorrectPasswordException extends ApiErrorException
{
    public function __construct(string $message = 'Current password is incorrect.')
    {
        parent::__construct(
            message: $message,
            errorCode: 'incorrect_password',
            status: Response::HTTP_BAD_REQUEST,
        );
    }
}
