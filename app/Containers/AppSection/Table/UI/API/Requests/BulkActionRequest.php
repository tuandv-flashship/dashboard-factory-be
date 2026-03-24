<?php

namespace App\Containers\AppSection\Table\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class BulkActionRequest extends ParentRequest
{
    protected array $access = [
        'permissions' => '',
        'roles' => '',
    ];

    protected array $decode = [
        'ids.*',
    ];

    public function rules(): array
    {
        $maxItems = config('appSection-table.max_bulk_items', 100);

        return [
            'model' => ['required', 'string', 'max:50'],
            'action' => ['required', 'string', 'max:50'],
            'ids' => ['required', 'array', 'min:1', "max:{$maxItems}"],
            'ids.*' => ['required', 'integer'],
        ];
    }

    public function authorize(): bool
    {
        // Permission is checked per-action inside BulkActionRegistry.
        return true;
    }
}
