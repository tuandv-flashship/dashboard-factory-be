<?php

namespace App\Ship\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class CreateResourceFailedException extends ApiErrorException
{
    public function __construct(string $message = 'Failed to create Resource.')
    {
        parent::__construct(
            message: $message,
            errorCode: 'resource_create_failed',
            status: Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }
}
