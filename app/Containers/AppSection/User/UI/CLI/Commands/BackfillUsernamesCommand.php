<?php

namespace App\Containers\AppSection\User\UI\CLI\Commands;

use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Commands\Command as ParentCommand;

final class BackfillUsernamesCommand extends ParentCommand
{
    protected $signature = 'backfill:usernames
                            {--dry-run : Show what would be changed without saving}';

    protected $description = 'Generate usernames for existing users that do not have one (derived from email)';

    public function handle(): int
    {
        $query = User::query()
            ->whereNull('username')
            ->whereNotNull('email');

        $count = $query->count();

        if ($count === 0) {
            $this->info('All users already have usernames. Nothing to do.');

            return self::SUCCESS;
        }

        $this->info("Found {$count} user(s) without username.");

        $isDryRun = $this->option('dry-run');
        $processed = 0;

        $query->each(function (User $user) use ($isDryRun, &$processed) {
            $username = User::generateUniqueUsername($user->email);

            if ($isDryRun) {
                $this->line("  [DRY-RUN] {$user->email} → {$username}");
            } else {
                $user->username = $username;
                $user->saveQuietly();
                $this->line("  ✓ {$user->email} → {$username}");
            }

            $processed++;
        });

        $verb = $isDryRun ? 'would be updated' : 'updated';
        $this->info("{$processed} user(s) {$verb}.");

        return self::SUCCESS;
    }
}
