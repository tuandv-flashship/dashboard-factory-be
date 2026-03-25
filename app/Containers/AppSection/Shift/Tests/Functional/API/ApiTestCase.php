<?php

namespace App\Containers\AppSection\Shift\Tests\Functional\API;

use App\Containers\AppSection\Shift\Tests\FunctionalTestCase;
use Spatie\Permission\Models\Permission;

class ApiTestCase extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $permissions = [
            'shift-templates.index',
            'shift-templates.create',
            'shift-templates.edit',
            'shift-templates.destroy',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'api');
        }
    }
}
