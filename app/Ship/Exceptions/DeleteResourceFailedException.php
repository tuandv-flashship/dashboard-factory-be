<?php

namespace App\Ship\Exceptions;

use Symfony\Component\HttpFoundation\Response;

class DeleteResourceFailedException extends ApiErrorException
{
    public function __construct(string $message = 'Failed to delete Resource.')
    {
        parent::__construct(
            message: $message,
            errorCode: 'resource_delete_failed',
            status: Response::HTTP_INTERNAL_SERVER_ERROR,
        );
    }
}
