<?php

namespace App\Ship\Parents\Transporters;

use Illuminate\Support\Fluent;

/**
 * Transporter - Data Transfer Object pattern for Apiato
 * 
 * Transporters are used to pass data between Controllers and Actions
 * in a type-safe, clean manner.
 * 
 * @see https://apiato.io/docs/optional-components/transporters
 */
abstract class Transporter extends Fluent
{
    /**
     * Create a new Transporter from array data
     */
    public static function fromArray(array $data): static
    {
        return new static($data);
    }

    /**
     * Create a new Transporter from a Request
     */
    public static function fromRequest(\Illuminate\Http\Request $request): static
    {
        return new static($request->validated());
    }

    /**
     * Get only specific keys from the transporter
     * 
     * @param array|string $keys
     */
    public function only($keys): static
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        return new static(collect($this->getAttributes())->only($keys)->toArray());
    }

    /**
     * Get all attributes except specific keys
     * 
     * @param array|string $keys
     */
    public function except($keys): static
    {
        $keys = is_array($keys) ? $keys : func_get_args();
        return new static(collect($this->getAttributes())->except($keys)->toArray());
    }

    /**
     * Check if a key exists and has a non-null value
     * 
     * @param string|array $key
     */
    public function has($key): bool
    {
        $keys = is_array($key) ? $key : [$key];
        
        foreach ($keys as $k) {
            if (!isset($this->attributes[$k])) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Get only specific keys as array
     */
    public function onlyAsArray(array $keys): array
    {
        return collect($this->getAttributes())
            ->only($keys)
            ->toArray();
    }

    /**
     * Get all attributes except specific keys as array
     */
    public function exceptAsArray(array $keys): array
    {
        return collect($this->getAttributes())
            ->except($keys)
            ->toArray();
    }
}
