<?php

namespace App\Ship\Values;

use App\Ship\Parents\Values\Value as ParentValue;

final readonly class ApiError extends ParentValue
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        public int $status,
        public string $message,
        public string $errorCode,
        public array $extra = [],
    ) {
    }

    /**
     * @param array<string, mixed> $extra
     */
    public static function create(int $status, string $message, string $errorCode, array $extra = []): self
    {
        return new self($status, $message, $errorCode, $extra);
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return array_merge([
            'message' => $this->message,
            'error_code' => $this->errorCode,
        ], $this->extra);
    }
}

