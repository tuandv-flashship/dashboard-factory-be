<?php

namespace App\Containers\AppSection\System\Tests\Functional\API;

use App\Containers\AppSection\System\Jobs\RunSystemCommandJob;
use App\Containers\AppSection\System\Tests\Functional\ApiTestCase;
use App\Containers\AppSection\System\UI\API\Controllers\RunSystemCommandController;
use App\Ship\Supports\SystemCommandStore;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RunSystemCommandController::class)]
final class RunSystemCommandTest extends ApiTestCase
{
    public function testRunSystemCommandQueuesJob(): void
    {
        config(['system-commands.enabled' => true]);
        Queue::fake();

        $this->actingAs(User::factory()->superAdmin()->createOne());

        $response = $this->postJson(action(RunSystemCommandController::class), [
            'action' => 'cache_clear',
        ]);

        $response->assertOk();

        $jobId = $response->json('data.job_id');
        $this->assertNotEmpty($jobId);

        $payload = SystemCommandStore::get($jobId);
        $this->assertNotNull($payload);
        $this->assertSame('queued', $payload['status']);

        Queue::assertPushed(RunSystemCommandJob::class);
    }

    public function testRunSystemCommandForbiddenWhenDisabled(): void
    {
        config(['system-commands.enabled' => false]);

        $this->actingAs(User::factory()->superAdmin()->createOne());

        $response = $this->postJson(action(RunSystemCommandController::class), [
            'action' => 'cache_clear',
        ]);

        $response->assertForbidden();
    }
}
