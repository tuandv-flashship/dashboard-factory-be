<?php

namespace App\Containers\AppSection\Table\BulkChanges;

final class NameBulkChange extends TextBulkChange
{
    protected string $name = 'name';
    protected string $title = 'table::columns.name';
    protected array|string|null $validate = 'required|string|max:250';
    protected ?string $placeholder = 'table::bulk_changes.enter_name';
}
