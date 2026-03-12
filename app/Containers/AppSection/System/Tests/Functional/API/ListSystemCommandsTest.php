<?php

namespace App\Containers\AppSection\System\Tests\Functional\API;

use App\Containers\AppSection\System\Tests\Functional\ApiTestCase;
use App\Containers\AppSection\System\UI\API\Controllers\ListSystemCommandsController;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ListSystemCommandsController::class)]
final class ListSystemCommandsTest extends ApiTestCase
{
    public function testListSystemCommands(): void
    {
        config(['system-commands.enabled' => true]);

        $this->actingAs(User::factory()->superAdmin()->createOne());

        $response = $this->getJson(action(ListSystemCommandsController::class));

        $response->assertOk();
        $response->assertJson(
            static fn (AssertableJson $json) => $json->has('data')
                ->where('data.0.action', 'cache_clear')
                ->etc(),
        );
    }
}
