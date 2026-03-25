<?php

namespace App\Containers\AppSection\KpiRatingLevel\Tests\Functional\API;

use App\Containers\AppSection\KpiRatingLevel\Tests\FunctionalTestCase;
use Spatie\Permission\Models\Permission;

class ApiTestCase extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $permissions = [
            'kpi-rating-levels.index',
            'kpi-rating-levels.create',
            'kpi-rating-levels.edit',
            'kpi-rating-levels.destroy',
        ];

        foreach ($permissions as $name) {
            Permission::findOrCreate($name, 'api');
        }
    }
}
