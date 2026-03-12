<?php

namespace App\Ship\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class UpdateResourceFailedException extends ApiErrorException
{
    public function __construct(string $message = 'Failed to update Resource.')
    {
        parent::__construct(
            message: $message,
            errorCode: 'resource_update_failed',
            status: Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }
}
