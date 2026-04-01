<?php

namespace App\Containers\AppSection\Production\Models;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class HourlyRecord extends ParentModel
{
    protected $table = 'hourly_records';

    protected $fillable = [
        'shift_id', 'department_id', 'hour_slot', 'hour_index',
        'target', 'actual', 'staff', 'hour_start_inventory', 'efficiency', 'error_rate',
    ];

    protected $casts = [
        'hour_index' => 'integer',
        'target' => 'integer',
        'actual' => 'integer',
        'staff' => 'integer',
        'hour_start_inventory' => 'integer',
        'efficiency' => 'float',
        'error_rate' => 'float',
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
