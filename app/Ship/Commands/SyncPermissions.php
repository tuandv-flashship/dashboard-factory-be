<?php

namespace App\Ship\Commands;

use Apiato\Core\Console\Command as ParentCommand;
use App\Ship\Supports\PermissionSyncer;

final class SyncPermissions extends ParentCommand
{
    protected $signature = 'apiato:permissions-sync
                            {--guard=* : Only sync specific guards}
                            {--include-web : Include the web guard}
                            {--prune : Remove permissions not defined in config}';

    protected $description = 'Sync permissions from container configs into the permissions table.';

    public function handle(PermissionSyncer $syncer): void
    {
        $guards = $this->option('guard');
        $includeWeb = (bool) $this->option('include-web');
        $prune = (bool) $this->option('prune');

        $result = $syncer->sync($guards, $includeWeb, $prune);

        if ($result['total'] === 0) {
            $this->warn('No permissions found in config.');
            return;
        }

        $this->info('Permissions synced.');
        $this->line('Guards: ' . implode(', ', $result['guards']));
        $this->line('Total: ' . $result['total']);
        $this->line('Created: ' . $result['created']);
        $this->line('Updated: ' . $result['updated']);
        $this->line('Unchanged: ' . $result['skipped']);

        if ($prune) {
            $this->line('Pruned: ' . $result['pruned']);
        }
    }
}
