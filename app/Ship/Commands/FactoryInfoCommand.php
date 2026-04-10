<?php

namespace App\Ship\Commands;

use Illuminate\Console\Command;

/**
 * Artisan command to show/verify the current factory context.
 *
 * This command is READ-ONLY — it never modifies .env.
 * To switch factory locally, use the shell helpers (see below).
 *
 * Usage:
 *   php artisan factory:info              # Show current factory
 *   FACTORY=PD php artisan factory:info   # Verify PD context
 *
 * Shell helpers (add to ~/.zshrc):
 *   alias fls='FACTORY=FLS DB_DATABASE=dashboard_fls_local'
 *   alias pd='FACTORY=PD DB_DATABASE=dashboard_pd_local'
 *
 * Then use:
 *   fls php artisan serve --port=8000
 *   pd  php artisan serve --port=8001
 *   fls php artisan migrate:fresh --seed
 *   pd  php artisan migrate:fresh --seed
 *   fls php artisan test
 */
final class FactoryInfoCommand extends Command
{
    protected $signature = 'factory:info';

    protected $description = 'Show the current factory context (read-only)';

    public function handle(): int
    {
        $factory = config('factory.current');
        $db = config('database.connections.mysql.database');
        $appName = config('app.name');
        $env = app()->environment();

        $this->newLine();

        $color = $factory === 'FLS' ? 'blue' : 'magenta';

        $this->info("  ┌──────────────────────────────────┐");
        $this->info("  │  🏭 Factory: <fg={$color};options=bold>{$factory}</>  ({$appName})");
        $this->info("  │  🗄️  Database: <fg=cyan>{$db}</>");
        $this->info("  │  🌍 Env: <fg=yellow>{$env}</>");
        $this->info("  └──────────────────────────────────┘");

        $this->newLine();

        if ($env === 'production') {
            $this->warn("  ⚠️  Running in PRODUCTION mode");
        }

        return self::SUCCESS;
    }
}
