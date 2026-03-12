<?php

namespace App\Containers\AppSection\Icon\Tests\Functional\API;

use App\Containers\AppSection\Icon\Tests\ContainerTestCase;
use App\Containers\AppSection\Icon\UI\API\Controllers\ListIconsController;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ListIconsController::class)]
final class IconApiTest extends ContainerTestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->createOne();
    }

    public function testListIcons(): void
    {
        $response = $this->actingAs($this->user, 'api')
            ->getJson('/v1/icons');

        $response->assertOk();
        $response->assertJson(
            static fn (AssertableJson $json): AssertableJson => $json
                ->has('data')
                ->etc(),
        );
    }

    public function testListIconsWithoutAuthFails(): void
    {
        $response = $this->getJson('/v1/icons');

        $this->assertContains($response->getStatusCode(), [401, 403, 500]);
    }
}
