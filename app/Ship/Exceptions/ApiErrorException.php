<?php

namespace App\Ship\Exceptions;

use App\Ship\Parents\Exceptions\Exception as ParentException;
use App\Ship\Values\ApiError;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

abstract class ApiErrorException extends ParentException
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        string $message,
        protected readonly string $errorCode,
        protected readonly int $status,
        protected readonly array $extra = [],
    ) {
        parent::__construct($message, $status);
    }

    public function render(Request $request): JsonResponse
    {
        $payload = ApiError::create(
            status: $this->status,
            message: $this->getMessage(),
            errorCode: $this->errorCode,
            extra: $this->extra,
        )->payload();

        return response()->json($payload, $this->status);
    }
}

