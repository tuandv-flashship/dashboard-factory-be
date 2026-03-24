<?php

namespace App\Containers\AppSection\Table\BulkChanges;

/**
 * Bulk change for is_featured boolean field (Yes/No select).
 */
final class IsFeaturedBulkChange extends SelectBulkChange
{
    protected string $name = 'is_featured';
    protected string $title = 'table::columns.is_featured';
    protected ?string $placeholder = 'table::bulk_changes.select';

    public function __construct()
    {
        $this->choices([
            '0' => 'table::bulk_changes.no',
            '1' => 'table::bulk_changes.yes',
        ]);
    }
}
