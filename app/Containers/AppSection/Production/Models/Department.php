<?php

namespace App\Containers\AppSection\Production\Models;

use App\Containers\AppSection\Production\Enums\DepartmentUnit;
use App\Containers\AppSection\Production\Enums\Factory;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Department extends ParentModel
{
    protected $table = 'departments';

    protected $fillable = [
        'production_line_id', 'code', 'label', 'label_en', 'icon', 'unit',
        'kpi_per_hour', 'factory', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'sort_order'   => 'integer',
        'is_active'    => 'boolean',
        'kpi_per_hour' => 'integer',
        'unit'         => DepartmentUnit::class,
        'factory'      => Factory::class,
    ];

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class, 'production_line_id');
    }

    public function hourlyRecords(): HasMany
    {
        return $this->hasMany(HourlyRecord::class, 'department_id')->orderBy('hour_index');
    }
}
