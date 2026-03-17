<?php

namespace App\Containers\AppSection\ReasonCode\Tests\Functional\API;

use App\Containers\AppSection\ReasonCode\Tests\FunctionalTestCase;
use Spatie\Permission\Models\Permission;

class ApiTestCase extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Create permissions that ReasonCode container uses
        $permissions = [
            'reason-codes.index',
            'reason-codes.create',
            'reason-codes.edit',
            'reason-codes.destroy',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'api');
        }
    }
}
