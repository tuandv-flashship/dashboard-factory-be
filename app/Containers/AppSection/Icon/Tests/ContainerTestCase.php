<?php

namespace App\Containers\AppSection\Icon\Tests;

use App\Ship\Parents\Tests\TestCase;
use Spatie\Permission\Models\Role;

class ContainerTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (array_keys(config('auth.guards')) as $guard) {
            Role::findOrCreate('admin', $guard);
        }
    }
}
