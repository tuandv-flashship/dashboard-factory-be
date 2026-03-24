<?php

namespace App\Containers\AppSection\Table\BulkChanges;

final class PhoneBulkChange extends TextBulkChange
{
    protected string $name = 'phone';
    protected string $title = 'table::bulk_changes.phone';
    protected array|string|null $validate = 'required|string|max:20|regex:/^[0-9+\-\s()]+$/';
    protected ?string $placeholder = 'table::bulk_changes.enter_phone';
}
