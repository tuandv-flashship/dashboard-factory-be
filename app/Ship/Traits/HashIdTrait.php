<?php

namespace App\Ship\Traits;

trait HashIdTrait
{
    /**
     * Hash ID helper for Transformers, Tasks, and Actions
     */
    protected function hashId(int|string|null $id): int|string|null
    {
        if ($id === null) {
            return null;
        }

        $intId = $this->normalizeIntegerId($id);
        if ($intId === null) {
            return $id;
        }

        if ($intId <= 0 || ! config('apiato.hash-id')) {
            return $intId;
        }

        return $this->encodeHashId($intId);
    }

    private function normalizeIntegerId(int|string $id): ?int
    {
        if (is_int($id)) {
            return $id;
        }

        if ($id === '' || preg_match('/^-?\d+$/', $id) !== 1) {
            return null;
        }

        return (int) $id;
    }

    private function encodeHashId(int $id): string
    {
        static $encoded = [];

        if (isset($encoded[$id])) {
            return $encoded[$id];
        }

        // Bound static memory growth for long-running workers.
        if (count($encoded) >= 10000) {
            $encoded = [];
        }

        $encoded[$id] = hashids()->encodeOrFail($id);

        return $encoded[$id];
    }
}
