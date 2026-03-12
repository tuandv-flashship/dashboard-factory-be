<?php

namespace App\Containers\AppSection\System\Actions;

use App\Ship\Parents\Actions\Action as ParentAction;
use App\Containers\AppSection\System\Jobs\RunSystemCommandJob;
use App\Ship\Supports\SystemCommandRegistry;
use App\Ship\Supports\SystemCommandStore;
use InvalidArgumentException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class RunSystemCommandAction extends ParentAction
{
    /**
     * @return array<string, mixed>
     */
    public function run(string $action, array $context = []): array
    {
        $definition = SystemCommandRegistry::find($action);
        if ($definition === null) {
            throw new InvalidArgumentException('Unsupported command action.');
        }

        $jobId = (string) Str::uuid();
        $normalizedAction = strtolower(trim($action));
        $command = $definition['command'];

        $payload = [
            'job_id' => $jobId,
            'action' => $normalizedAction,
            'command' => $command,
            'status' => 'queued',
            'exit_code' => null,
            'output' => null,
            'error' => null,
            'started_at' => null,
            'finished_at' => null,
            'created_at' => Carbon::now()->toIso8601String(),
        ];

        SystemCommandStore::put($jobId, $payload);

        RunSystemCommandJob::dispatch($jobId, $normalizedAction, $context);

        return $payload;
    }
}
