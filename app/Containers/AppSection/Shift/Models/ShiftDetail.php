<?php

namespace App\Containers\AppSection\Shift\Models;

use App\Containers\AppSection\Department\Models\Department;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

final class ShiftDetail extends ParentModel
{
    protected $table = 'shift_details';

    protected $fillable = [
        'shift_id',
        'department_id',
        'shift_number',
        'headcount',
        'machine_count',
        'kpi_per_hour',
        'day_start_inventory',
        'hotshot_total',
        'hotshot_completed',
        'start_time',
        'work_hours',
        'prep_minutes',
        'break1_start',
        'break1_minutes',
        'meal_break_start',
        'meal_break_minutes',
        'break2_start',
        'break2_minutes',
        'break3_start',
        'break3_minutes',
    ];

    protected $casts = [
        'shift_number'       => 'integer',
        'headcount'          => 'integer',
        'machine_count'      => 'integer',
        'kpi_per_hour'       => 'integer',
        'day_start_inventory'=> 'integer',
        'hotshot_total'      => 'integer',
        'hotshot_completed'  => 'integer',
        'work_hours'         => 'decimal:1',
        'prep_minutes'       => 'integer',
        'break1_minutes'     => 'integer',
        'meal_break_minutes' => 'integer',
        'break2_minutes'     => 'integer',
        'break3_minutes'     => 'integer',
    ];

    // ── Relationships ────────────────────────────────────

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class, 'shift_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function machines(): HasMany
    {
        return $this->hasMany(ShiftDetailMachine::class, 'shift_detail_id');
    }

    // ── Accessors ────────────────────────────────────────

    /**
     * Auto-compute end_time = start_time + work_hours + meal_break.
     *
     * work_hours = net productive time (e.g. 8h).
     * meal_break is added on top (e.g. 30 min) → total presence.
     */
    protected function endTime(): Attribute
    {
        return Attribute::get(function (): ?string {
            if ($this->start_time === null || $this->work_hours === null) {
                return null;
            }

            $totalMinutes = (int) ($this->work_hours * 60) + ($this->meal_break_minutes ?? 0);

            return Carbon::createFromFormat('H:i:s', $this->start_time)
                ->addMinutes($totalMinutes)
                ->format('H:i');
        });
    }
}
