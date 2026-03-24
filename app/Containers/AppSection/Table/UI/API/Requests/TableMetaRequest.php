<?php

namespace App\Containers\AppSection\Table\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class TableMetaRequest extends ParentRequest
{
    protected array $access = [
        'permissions' => '',
        'roles' => '',
    ];

    public function rules(): array
    {
        return [
            'model' => ['nullable', 'string', 'max:50'],
        ];
    }

    public function authorize(): bool
    {
        // Any authenticated user can request table meta.
        // Permissions are filtered in the response by BulkActionRegistry.
        return true;
    }
}
