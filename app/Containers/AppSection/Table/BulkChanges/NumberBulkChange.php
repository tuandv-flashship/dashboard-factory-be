<?php

namespace App\Containers\AppSection\Table\BulkChanges;

use App\Containers\AppSection\Table\Abstracts\BulkChangeAbstract;

final class NumberBulkChange extends BulkChangeAbstract
{
    protected string $type = 'number';
    protected array|string|null $validate = 'required|integer|min:0';
    protected ?string $placeholder = 'table::bulk_changes.enter_number';
}
