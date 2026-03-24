<?php

namespace App\Containers\AppSection\Table\Events;

use Illuminate\Queue\SerializesModels;

final class BulkActionCompleted
{
    use SerializesModels;

    public function __construct(
        public readonly string $action,
        public readonly string $modelKey,
        public readonly array $successIds,
        public readonly int $successCount,
        public readonly int $failedCount,
    ) {
    }
}
