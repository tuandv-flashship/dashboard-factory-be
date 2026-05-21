<?php

use App\Containers\AppSection\Shift\Data\Repositories\ShiftTemplateRepository;
use App\Containers\AppSection\Shift\Enums\ShiftTemplateStatus;
use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Containers\AppSection\Shift\UI\API\Requests\CreateShiftTemplateRequest;
use App\Containers\AppSection\Shift\UI\API\Requests\UpdateShiftTemplateRequest;
use App\Containers\AppSection\Table\Abstracts\ColumnDefinition;
use App\Containers\AppSection\Table\Abstracts\FormFieldDefinition;

/*
|--------------------------------------------------------------------------
| Shift Container — Table & Form Metadata
|--------------------------------------------------------------------------
|
| Registered models for table-meta and form-meta APIs.
| File convention: table-models.php → auto-discovered by Table container.
|
*/

return [
    'shift_template' => [
        'model'             => ShiftTemplate::class,
        'repository'        => ShiftTemplateRepository::class,
        'permission_prefix' => 'shift-templates',
        'permission'        => null,
        'api_prefix'        => '/v1/admin/shift-templates',
        'fe_prefix'         => '/shift-templates',
        'default_sort'      => ['key' => 'sort_order', 'direction' => 'asc'],
        'pagination'        => ['default_limit' => 15, 'limits' => [15, 30, 50, 100]],

        'columns' => [
            ColumnDefinition::make('color', 'table::columns.color')
                ->type('color')->sortable(false)->width(60)->align('center')->priority(1),
            ColumnDefinition::make('name', 'table::columns.name')
                ->searchable()->priority(2),
            ColumnDefinition::make('status', 'table::columns.status')
                ->width(100)->align('center')->priority(3)
                ->searchable()->enum(ShiftTemplateStatus::class),
            ColumnDefinition::number('sort_order', 'table::columns.sort_order')
                ->width(80)->priority(4),
            ColumnDefinition::make('description', 'table::columns.description')
                ->emptyState()->priority(5),
            ColumnDefinition::boolean('applies_to_shift_1', 'table::columns.applies_to_shift_1')
                ->width(80)->priority(6),
            ColumnDefinition::boolean('applies_to_shift_2', 'table::columns.applies_to_shift_2')
                ->width(80)->priority(7),
        ],

        'forms' => [
            'create' => [
                'request'    => CreateShiftTemplateRequest::class,
                'permission' => 'shift-templates.create',
                'submit'     => ['method' => 'POST', 'url' => '/v1/admin/shift-templates'],
                'groups'     => [
                    ['key' => 'basic', 'label' => 'table::groups.basic', 'order' => 0],
                    ['key' => 'settings', 'label' => 'table::groups.settings', 'order' => 1],
                ],
                'overrides' => [
                    FormFieldDefinition::text('name')->group('basic')->order(0),
                    FormFieldDefinition::color('color')->group('basic')->order(1),
                    FormFieldDefinition::textarea('description')->group('basic')->order(2)->colSpan(2),
                    FormFieldDefinition::number('sort_order')->group('settings')->order(0)->default(0),
                    FormFieldDefinition::select('status')->group('settings')->order(1),
                    FormFieldDefinition::boolean('applies_to_shift_1')->group('settings')->order(2)->default(true),
                    FormFieldDefinition::boolean('applies_to_shift_2')->group('settings')->order(3)->default(false),
                ],
            ],
            'update' => [
                'request'    => UpdateShiftTemplateRequest::class,
                'permission' => 'shift-templates.edit',
                'submit'     => ['method' => 'PATCH', 'url' => '/v1/admin/shift-templates/{id}'],
            ],
        ],
    ],
];
