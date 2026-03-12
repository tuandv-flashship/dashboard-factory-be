<?php

namespace App\Containers\AppSection\System\UI\API\Transformers;

use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class SystemPackageTransformer extends ParentTransformer
{
    /**
     * @param array<string, mixed> $package
     */
    public function transform(array $package): array
    {
        $dependencies = $package['dependencies'] ?? [];
        $devDependencies = $package['dev_dependencies'] ?? [];

        if (! is_array($dependencies)) {
            $dependencies = [];
        }

        if (! is_array($devDependencies)) {
            $devDependencies = [];
        }

        return [
            'type' => 'SystemPackage',
            'id' => (string) ($package['name'] ?? ''),
            'name' => $package['name'] ?? null,
            'version' => $package['version'] ?? null,
            'dependencies' => $dependencies,
            'dev_dependencies' => $devDependencies,
        ];
    }
}
