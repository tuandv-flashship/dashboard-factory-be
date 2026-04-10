<?php

namespace App\Containers\AppSection\Shift\Models;

use App\Containers\AppSection\Machine\Models\Machine;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ShiftDetailMachine extends ParentModel
{
    protected $table = 'shift_detail_machines';

    protected $fillable = [
        'shift_detail_id',
        'machine_id',
        'kpi_per_hour',
    ];

    protected $casts = [
        'kpi_per_hour' => 'integer',
    ];

    public function shiftDetail(): BelongsTo
    {
        return $this->belongsTo(ShiftDetail::class, 'shift_detail_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }
}
