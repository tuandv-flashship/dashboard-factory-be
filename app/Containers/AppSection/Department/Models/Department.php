<?php

namespace App\Containers\AppSection\Department\Models;

use App\Containers\AppSection\Department\Enums\DepartmentUnit;
use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Machine\Models\Machine;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Department extends ParentModel
{
    protected $table = 'departments';

    protected $fillable = [
        'production_line_id', 'code', 'label', 'label_en', 'description', 'icon', 'unit',
        'kpi_per_hour', 'factory', 'sort_order', 'is_active', 'productivity_type',
    ];

    protected $casts = [
        'sort_order'        => 'integer',
        'is_active'         => 'boolean',
        'kpi_per_hour'      => 'integer',
        'unit'              => DepartmentUnit::class,
        'productivity_type' => ProductivityType::class,
    ];

    /**
     * Explicit accessor for the `factory` DB column to prevent
     * conflict with HasFactory trait's factory() method.
     */
    public function getFactoryAttribute(): ?string
    {
        return $this->attributes['factory'] ?? null;
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class, 'production_line_id');
    }

    public function hourlyRecords(): HasMany
    {
        return $this->hasMany(HourlyRecord::class, 'department_id')->orderBy('hour_index');
    }

    public function machines(): HasMany
    {
        return $this->hasMany(Machine::class, 'department_id')->orderBy('sort_order');
    }
}
