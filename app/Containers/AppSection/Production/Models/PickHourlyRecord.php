<?php

namespace App\Containers\AppSection\Production\Models;

use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PickHourlyRecord extends ParentModel
{
    protected $table = 'pick_hourly_records';

    protected $fillable = [
        'shift_id', 'production_line_id', 'hour_slot', 'hour_index',
        'target', 'actual', 'staff', 'efficiency', 'error_rate', 'total_picked',
    ];

    protected $casts = [
        'hour_index' => 'integer',
        'target' => 'integer',
        'actual' => 'integer',
        'staff' => 'integer',
        'efficiency' => 'float',
        'error_rate' => 'float',
        'total_picked' => 'integer',
    ];

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function productionLine(): BelongsTo
    {
        return $this->belongsTo(ProductionLine::class, 'production_line_id');
    }
}
