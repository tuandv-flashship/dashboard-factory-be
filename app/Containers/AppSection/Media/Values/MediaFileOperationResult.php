<?php

namespace App\Containers\AppSection\Media\Values;

final class MediaFileOperationResult
{
    /**
     * @param array<string, mixed>|null $data
     */
    private function __construct(
        private readonly int $status,
        private readonly ?array $data = null,
        private readonly ?string $message = null,
        private readonly ?string $errorCode = null,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function success(array $data): self
    {
        return new self(status: 200, data: $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function accepted(array $data): self
    {
        return new self(status: 202, data: $data);
    }

    public static function validationError(string $message, string $errorCode = 'validation_error'): self
    {
        return new self(status: 422, message: $message, errorCode: $errorCode);
    }

    public static function payloadTooLarge(
        string $message = 'File size exceeds the allowed limit.',
        string $errorCode = 'file_too_large',
    ): self {
        return new self(status: 413, message: $message, errorCode: $errorCode);
    }

    public static function externalServiceError(
        string $message = 'Unable to process external media resource.',
        string $errorCode = 'external_service_error',
    ): self {
        return new self(status: 502, message: $message, errorCode: $errorCode);
    }

    public static function internalError(
        string $message = 'Media operation failed.',
        string $errorCode = 'internal_error',
    ): self {
        return new self(status: 500, message: $message, errorCode: $errorCode);
    }

    public function status(): int
    {
        return $this->status;
    }

    /**
     * @return array{data: array<string, mixed>}|array{message: string,error_code: string}
     */
    public function responseBody(): array
    {
        if ($this->data !== null) {
            return ['data' => $this->data];
        }

        return [
            'message' => $this->message ?? 'Invalid media request.',
            'error_code' => $this->errorCode ?? 'media_error',
        ];
    }
}
