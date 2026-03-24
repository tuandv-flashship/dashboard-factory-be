<?php

namespace App\Containers\AppSection\Table\BulkChanges;

final class EmailBulkChange extends TextBulkChange
{
    protected string $name = 'email';
    protected string $title = 'table::columns.email';
    protected array|string|null $validate = 'required|email|max:120';
    protected ?string $placeholder = 'table::bulk_changes.enter_email';
}
