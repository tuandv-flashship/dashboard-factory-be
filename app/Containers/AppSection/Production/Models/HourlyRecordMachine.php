<?php

namespace App\Containers\AppSection\Production\Models;

use App\Containers\AppSection\Machine\Models\Machine;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HourlyRecordMachine extends ParentModel
{
    protected $table = 'hourly_record_machines';

    protected $fillable = [
        'hourly_record_id',
        'machine_id',
        'kpi_per_hour',
    ];

    protected $casts = [
        'kpi_per_hour' => 'integer',
    ];

    public function hourlyRecord(): BelongsTo
    {
        return $this->belongsTo(HourlyRecord::class, 'hourly_record_id');
    }

    public function machine(): BelongsTo
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }
}
