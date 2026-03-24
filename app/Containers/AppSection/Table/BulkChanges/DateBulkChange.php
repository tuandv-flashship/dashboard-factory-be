<?php

namespace App\Containers\AppSection\Table\BulkChanges;

use App\Containers\AppSection\Table\Abstracts\BulkChangeAbstract;

class DateBulkChange extends BulkChangeAbstract
{
    protected string $type = 'date';
    protected array|string|null $validate = 'required|string|date';
    protected ?string $placeholder = 'table::bulk_changes.select_date';
}
