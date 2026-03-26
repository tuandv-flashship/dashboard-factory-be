<?php

use App\Containers\AppSection\Production\Data\Repositories\ProductionLineRepository;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Production\UI\API\Requests\CreateProductionLineRequest;
use App\Containers\AppSection\Production\UI\API\Requests\UpdateProductionLineRequest;
use App\Containers\AppSection\Table\Abstracts\ColumnDefinition;
use App\Containers\AppSection\Table\Abstracts\FormFieldDefinition;

/*
|--------------------------------------------------------------------------
| Production Container — Table & Form Metadata
|--------------------------------------------------------------------------
|
| Registered models for table-meta and form-meta APIs.
| File convention: table-models.php → auto-discovered by Table container.
|
*/

return [
    'production_line' => [
        'model'             => ProductionLine::class,
        'repository'        => ProductionLineRepository::class,
        'permission_prefix' => 'production-lines',
        'permission'        => 'production-lines.index',
        'api_prefix'        => '/v1/admin/production-lines',
        'fe_prefix'         => '/production-lines',
        'default_sort'      => ['key' => 'sort_order', 'direction' => 'asc'],
        'pagination'        => ['default_limit' => 15, 'limits' => [15, 30, 50, 100]],

        'columns' => [
            ColumnDefinition::make('code', 'table::columns.code')
                ->searchable()->width(100)->priority(2),
            ColumnDefinition::make('label', 'table::columns.label')
                ->searchable()->priority(3),
            ColumnDefinition::make('color', 'table::columns.color')
                ->type('color')->sortable(false)->width(80)->align('center')->priority(4),
            ColumnDefinition::make('subtitle', 'table::columns.subtitle')
                ->emptyState()->priority(5),
            ColumnDefinition::boolean('is_shared', 'table::columns.is_shared')
                ->width(100)->priority(6),
            ColumnDefinition::boolean('is_active', 'table::columns.is_active')
                ->width(100)->priority(7),
            ColumnDefinition::number('sort_order', 'table::columns.sort_order')
                ->width(80)->priority(8),
        ],

        'forms' => [
            'create' => [
                'request'    => CreateProductionLineRequest::class,
                'permission' => 'production-lines.create',
                'submit'     => ['method' => 'POST', 'url' => '/v1/admin/production-lines'],
                'groups'     => [
                    ['key' => 'basic', 'label' => 'table::groups.basic', 'order' => 0],
                    ['key' => 'settings', 'label' => 'table::groups.settings', 'order' => 1],
                ],
                'overrides' => [
                    FormFieldDefinition::text('code')->group('basic')->order(0),
                    FormFieldDefinition::text('label')->group('basic')->order(1),
                    FormFieldDefinition::color('color')->group('basic')->order(2),
                    FormFieldDefinition::textarea('subtitle')->group('basic')->order(3)->colSpan(2),
                    FormFieldDefinition::boolean('is_shared')->group('settings')->order(0),
                    FormFieldDefinition::boolean('is_active')->group('settings')->order(1)->default(true),
                    FormFieldDefinition::number('sort_order')->group('settings')->order(2)->default(0),
                ],
            ],
            'update' => [
                'request'    => UpdateProductionLineRequest::class,
                'permission' => 'production-lines.edit',
                'submit'     => ['method' => 'PATCH', 'url' => '/v1/admin/production-lines/{id}'],
            ],
        ],
    ],
];
