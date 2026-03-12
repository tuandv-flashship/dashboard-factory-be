<?php

namespace App\Containers\AppSection\System\Jobs;

use App\Ship\Supports\SystemCommandRegistry;
use App\Ship\Supports\SystemCommandStore;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Throwable;

final class RunSystemCommandJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly string $jobId,
        public readonly string $action,
        public readonly array $context = [],
    ) {
    }

    public function handle(): void
    {
        $definition = SystemCommandRegistry::find($this->action);
        if ($definition === null) {
            $this->storeResult([
                'status' => 'failed',
                'error' => 'Unsupported command action.',
                'finished_at' => Carbon::now()->toIso8601String(),
            ]);
            return;
        }

        $command = $definition['command'];
        $options = $definition['options'] ?? [];

        $this->storeResult([
            'status' => 'running',
            'started_at' => Carbon::now()->toIso8601String(),
        ]);

        try {
            $exitCode = Artisan::call($command, $options);
            $output = Artisan::output();
            $status = $exitCode === 0 ? 'succeeded' : 'failed';

            $this->storeResult([
                'status' => $status,
                'exit_code' => $exitCode,
                'output' => $output,
                'finished_at' => Carbon::now()->toIso8601String(),
            ]);

            Log::info('system_command.executed', array_merge($this->context, [
                'job_id' => $this->jobId,
                'action' => $this->action,
                'command' => $command,
                'exit_code' => $exitCode,
                'status' => $status,
            ]));
        } catch (Throwable $exception) {
            $this->storeResult([
                'status' => 'failed',
                'error' => $exception->getMessage(),
                'finished_at' => Carbon::now()->toIso8601String(),
            ]);

            Log::error('system_command.failed', array_merge($this->context, [
                'job_id' => $this->jobId,
                'action' => $this->action,
                'command' => $command,
                'error' => $exception->getMessage(),
            ]));
        }
    }

    /**
     * @param array<string, mixed> $updates
     */
    private function storeResult(array $updates): void
    {
        $current = SystemCommandStore::get($this->jobId) ?? [];
        $payload = array_merge($current, $updates);

        SystemCommandStore::put($this->jobId, $payload);
    }
}
