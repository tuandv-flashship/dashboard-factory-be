<?php

namespace App\Ship\Parents\Requests;

use Apiato\Core\Requests\Request as AbstractRequest;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

abstract class Request extends AbstractRequest
{
    /**
     * Auto-decode HashId fields listed in $decode before validation runs.
     *
     * Apiato's $decode only decodes on read (via input()), but validation
     * still sees raw HashId strings. This override mutates the request data
     * so 'integer' and 'exists' rules work correctly with decoded IDs.
     *
     * Child classes should call parent::prepareForValidation() when overriding.
     */
    protected function prepareForValidation(): void
    {
        if (empty($this->decode) || ! config('apiato.hash-id')) {
            return;
        }

        // Read raw request data bypassing Apiato's input() override
        $data = $this->json()->all() ?: request()->query->all();
        $flattened = Arr::dot($data);
        $decoded = [];
        $matchedPatterns = [];

        foreach ($flattened as $dotKey => $value) {
            foreach ($this->decode as $pattern) {
                if (Str::is($pattern, $dotKey) && is_string($value)) {
                    $result = hashids()->decode($value);
                    if ($result !== null) {
                        Arr::set($decoded, $dotKey, $result);
                        $matchedPatterns[$pattern] = true;
                    }
                    break;
                }
            }
        }

        if (! empty($decoded)) {
            $this->merge($decoded);

            // Remove only the patterns that matched body input fields.
            // Route param patterns (user_id, role_id, etc.) are preserved
            // since they don't appear in the JSON body.
            $this->decode = array_values(
                array_filter($this->decode, static fn (string $p): bool => ! isset($matchedPatterns[$p]))
            );
        }
    }
}
