<?php

use App\Containers\AppSection\Department\Data\Repositories\DepartmentRepository;
use App\Containers\AppSection\Department\Enums\DepartmentUnit;
use App\Containers\AppSection\Department\Enums\Factory;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Department\UI\API\Requests\CreateDepartmentRequest;
use App\Containers\AppSection\Department\UI\API\Requests\UpdateDepartmentRequest;
use App\Containers\AppSection\Table\Abstracts\ColumnDefinition;
use App\Containers\AppSection\Table\Abstracts\FormFieldDefinition;

/*
|--------------------------------------------------------------------------
| Department Container — Table & Form Metadata
|--------------------------------------------------------------------------
|
| Registered models for table-meta and form-meta APIs.
| File convention: table-models.php → auto-discovered by Table container.
|
*/

return [
    'department' => [
        'model'             => Department::class,
        'repository'        => DepartmentRepository::class,
        'permission_prefix' => 'departments',
        'permission'        => 'departments.index',
        'api_prefix'        => '/v1/admin/departments',
        'fe_prefix'         => '/departments',
        'default_sort'      => ['key' => 'sort_order', 'direction' => 'asc'],
        'pagination'        => ['default_limit' => 15, 'limits' => [15, 30, 50, 100]],

        'columns' => [
            ColumnDefinition::make('code', 'table::columns.code')
                ->searchable()->width(100)->priority(2),
            ColumnDefinition::make('label', 'table::columns.label')
                ->searchable()->priority(3),
            ColumnDefinition::make('label_en', 'table::columns.label_en')
                ->priority(4),
            ColumnDefinition::make('icon', 'table::columns.icon')
                ->type('icon')->sortable(false)->width(60)->align('center')->priority(5),
            ColumnDefinition::make('unit', 'table::columns.unit')
                ->width(80)->align('center')->priority(6)
                ->searchable()->enum(DepartmentUnit::class),
            ColumnDefinition::number('kpi_per_hour', 'table::columns.kpi_per_hour')
                ->width(100)->align('right')->priority(7),
            ColumnDefinition::make('factory', 'table::columns.factory')
                ->width(80)->align('center')->priority(8)
                ->searchable()->enum(Factory::class),
            ColumnDefinition::boolean('is_active', 'table::columns.is_active')
                ->width(100)->priority(9),
            ColumnDefinition::number('sort_order', 'table::columns.sort_order')
                ->width(80)->priority(10),
        ],

        'forms' => [
            'create' => [
                'request'    => CreateDepartmentRequest::class,
                'permission' => 'departments.create',
                'submit'     => ['method' => 'POST', 'url' => '/v1/admin/departments'],
                'groups'     => [
                    ['key' => 'basic', 'label' => 'table::groups.basic', 'order' => 0],
                    ['key' => 'settings', 'label' => 'table::groups.settings', 'order' => 1],
                ],
                'overrides' => [
                    FormFieldDefinition::relation('production_line_id', 'table::fields.production_line_id')
                        ->endpoint('/v1/admin/production-lines')
                        ->labelField('label')->valueField('id')
                        ->group('basic')->order(0),
                    FormFieldDefinition::text('code')->group('basic')->order(1),
                    FormFieldDefinition::text('label')->group('basic')->order(2),
                    FormFieldDefinition::text('label_en')->group('basic')->order(3),
                    FormFieldDefinition::icon('icon')->group('basic')->order(4),
                    FormFieldDefinition::select('unit')
                        ->group('basic')->order(5),
                    FormFieldDefinition::number('kpi_per_hour')
                        ->group('settings')->order(0),
                    FormFieldDefinition::select('factory')
                        ->group('settings')->order(1),
                    FormFieldDefinition::boolean('is_active')
                        ->group('settings')->order(2)->default(true),
                    FormFieldDefinition::number('sort_order')
                        ->group('settings')->order(3)->default(0),
                ],
            ],
            'update' => [
                'request'    => UpdateDepartmentRequest::class,
                'permission' => 'departments.edit',
                'submit'     => ['method' => 'PATCH', 'url' => '/v1/admin/departments/{id}'],
            ],
        ],
    ],
];
