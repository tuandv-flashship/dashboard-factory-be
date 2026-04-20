<?php

namespace App\Containers\AppSection\Production\Models;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

final class HourlyRecord extends ParentModel
{
    use SoftDeletes;
    protected $table = 'hourly_records';

    protected $fillable = [
        'shift_id', 'department_id', 'hour_slot', 'hour_index',
        'target', 'kpi_hours', 'kpi_minutes', 'kpi_percent', 'actual', 'staff', 'staff_required',
        'hour_start_inventory',
        'efficiency', 'error_rate', 'status', 'productivity_json',
    ];

    protected $casts = [
        'hour_index'           => 'integer',
        'target'               => 'integer',
        'kpi_hours'            => 'float',
        'kpi_minutes'          => 'integer',
        'kpi_percent'          => 'float',
        'actual'               => 'integer',
        'staff'                => 'integer',
        'staff_required'       => 'integer',
        'hour_start_inventory' => 'integer',
        'efficiency'           => 'float',
        'error_rate'           => 'float',
        'status'               => 'string',
        'productivity_json'    => 'array',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function issues(): HasMany
    {
        return $this->hasMany(HourlyIssue::class, 'hourly_record_id');
    }
}
