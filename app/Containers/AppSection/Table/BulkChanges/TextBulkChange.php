<?php

namespace App\Containers\AppSection\Table\BulkChanges;

use App\Containers\AppSection\Table\Abstracts\BulkChangeAbstract;

/**
 * Generic text input bulk change. Base for NameBulkChange, EmailBulkChange, etc.
 */
class TextBulkChange extends BulkChangeAbstract
{
    protected string $type = 'text';
    protected array|string|null $validate = 'required|string|max:255';
}
