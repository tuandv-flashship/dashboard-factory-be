<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Horizon Name
    |--------------------------------------------------------------------------
    |
    | Display name in the Horizon dashboard. Uses FACTORY env to distinguish
    | between FLS and PD instances when running multiple Horizon processes.
    |
    */

    'name' => env('HORIZON_NAME', env('FACTORY', 'Dashboard') . ' Horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Domain
    |--------------------------------------------------------------------------
    */

    'domain' => env('HORIZON_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Path
    |--------------------------------------------------------------------------
    |
    | Dashboard accessible at /horizon
    |
    */

    'path' => env('HORIZON_PATH', 'horizon'),

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Connection
    |--------------------------------------------------------------------------
    |
    | Uses the 'default' Redis connection. REDIS_PREFIX in .env ensures
    | FLS and PD data is isolated (fls_horizon: vs pd_horizon:).
    |
    */

    'use' => 'default',

    /*
    |--------------------------------------------------------------------------
    | Horizon Redis Prefix
    |--------------------------------------------------------------------------
    |
    | Uses REDIS_PREFIX from .env to isolate Horizon data per factory:
    |   FLS → fls_horizon:
    |   PD  → pd_horizon:
    |
    */

    'prefix' => env(
        'HORIZON_PREFIX',
        env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_') . '_') . 'horizon:'
    ),

    /*
    |--------------------------------------------------------------------------
    | Horizon Route Middleware
    |--------------------------------------------------------------------------
    */

    'middleware' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Queue Wait Time Thresholds
    |--------------------------------------------------------------------------
    |
    | Fire LongWaitDetected event when queue wait exceeds threshold (seconds).
    |
    */

    'waits' => [
        'redis:default' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Job Trimming Times (minutes)
    |--------------------------------------------------------------------------
    |
    | recent/completed: 1 hour, failed: 7 days
    |
    */

    'trim' => [
        'recent' => 60,
        'pending' => 60,
        'completed' => 60,
        'recent_failed' => 10080,
        'failed' => 10080,
        'monitored' => 10080,
    ],

    /*
    |--------------------------------------------------------------------------
    | Silenced Jobs
    |--------------------------------------------------------------------------
    |
    | Jobs that won't appear in the "Completed Jobs" dashboard list.
    |
    */

    'silenced' => [
        // App\Jobs\ExampleJob::class,
    ],

    'silenced_tags' => [
        // 'notifications',
    ],

    /*
    |--------------------------------------------------------------------------
    | Metrics
    |--------------------------------------------------------------------------
    |
    | Number of metric snapshots to retain for dashboard graphs.
    | Combined with `horizon:snapshot` schedule command.
    |
    */

    'metrics' => [
        'trim_snapshots' => [
            'job' => 24,
            'queue' => 24,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Fast Termination
    |--------------------------------------------------------------------------
    |
    | When enabled, `horizon:terminate` won't wait for workers to finish.
    | New Horizon instance starts while old one gracefully terminates.
    |
    */

    'fast_termination' => false,

    /*
    |--------------------------------------------------------------------------
    | Memory Limit (MB)
    |--------------------------------------------------------------------------
    |
    | Max memory for the Horizon master supervisor before auto-restart.
    |
    */

    'memory_limit' => (int) env('HORIZON_MEMORY_LIMIT', 256),

    /*
    |--------------------------------------------------------------------------
    | Queue Worker Configuration
    |--------------------------------------------------------------------------
    |
    | Per-environment worker configuration. Horizon auto-selects based on
    | APP_ENV. Each factory (FLS/PD) runs its own Horizon process.
    |
    | Queues:
    |   - default: general jobs (CreateDailyShiftJob, SyncHourlyRecordsJob)
    |   - sync: parallel inventory fetch (FetchTeamInventoryJob) + dept sync (SyncDepartmentHourlyJob)
    |   - notifications: email notifications (Welcome, EmailVerified, etc.)
    |   - media: thumbnail generation (GenerateThumbnailsJob)
    |
    */

    'defaults' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'sync', 'notifications', 'media'],
            'balance' => 'auto',
            'autoScalingStrategy' => 'time',
            'maxProcesses' => (int) env('HORIZON_MAX_PROCESSES', 20),
            'maxTime' => 0,
            'maxJobs' => 0,
            'memory' => 128,
            'tries' => 3,
            'timeout' => 120,
            'nice' => 0,
        ],
    ],

    'environments' => [
        'production' => [
            'supervisor-1' => [
                'maxProcesses' => (int) env('HORIZON_MAX_PROCESSES', 20),
                'balanceMaxShift' => 1,
                'balanceCooldown' => 3,
            ],
        ],

        'staging' => [
            'supervisor-1' => [
                'maxProcesses' => (int) env('HORIZON_MAX_PROCESSES', 20),
            ],
        ],

        'local' => [
            'supervisor-1' => [
                'maxProcesses' => (int) env('HORIZON_MAX_PROCESSES', 20),
            ],
        ],

        // Fallback for APP_ENV=fls or APP_ENV=pd (factory-env.sh)
        '*' => [
            'supervisor-1' => [
                'maxProcesses' => (int) env('HORIZON_MAX_PROCESSES', 20),
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File Watcher Configuration
    |--------------------------------------------------------------------------
    |
    | Directories/files watched by `horizon:listen` for auto-restart.
    |
    */

    'watch' => [
        'app',
        'bootstrap',
        'config/**/*.php',
        'database/**/*.php',
        'public/**/*.php',
        'resources/**/*.php',
        'routes',
        'composer.lock',
        'composer.json',
        '.env',
    ],
];
