<?php

namespace App\Containers\AppSection\System\Tests\Functional\API;

use App\Containers\AppSection\System\Tests\Functional\ApiTestCase;
use App\Containers\AppSection\System\UI\API\Controllers\GetSystemCommandStatusController;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Supports\SystemCommandStore;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GetSystemCommandStatusController::class)]
final class GetSystemCommandStatusTest extends ApiTestCase
{
    public function testGetSystemCommandStatus(): void
    {
        config(['system-commands.enabled' => true]);

        $this->actingAs(User::factory()->superAdmin()->createOne());

        $jobId = 'test-job';
        $now = CarbonImmutable::now()->toIso8601String();

        SystemCommandStore::put($jobId, [
            'job_id' => $jobId,
            'action' => 'cache_clear',
            'command' => 'cache:clear',
            'status' => 'succeeded',
            'exit_code' => 0,
            'output' => 'done',
            'created_at' => $now,
            'finished_at' => $now,
        ]);

        $response = $this->getJson(action(GetSystemCommandStatusController::class, ['job_id' => $jobId]));

        $response->assertOk();
        $response->assertJsonPath('data.status', 'succeeded');
    }
}
