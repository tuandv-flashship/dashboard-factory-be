<?php

namespace App\Containers\AppSection\Table\BulkChanges;

final class CreatedAtBulkChange extends DateBulkChange
{
    protected string $name = 'created_at';
    protected string $title = 'table::columns.created_at';
    protected string $type = 'datePicker';
}
