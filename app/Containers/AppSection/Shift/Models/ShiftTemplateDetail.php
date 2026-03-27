<?php

namespace App\Containers\AppSection\Shift\Models;

use App\Containers\AppSection\Department\Models\Department;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

final class ShiftTemplateDetail extends ParentModel
{
    protected $table = 'shift_template_details';

    protected $fillable = [
        'shift_template_id',
        'department_id',
        'shift_number',
        'headcount',
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
        'work_hours'         => 'decimal:1',
        'prep_minutes'       => 'integer',
        'break1_minutes'     => 'integer',
        'meal_break_minutes' => 'integer',
        'break2_minutes'     => 'integer',
        'break3_minutes'     => 'integer',
    ];

    // ── Relationships ────────────────────────────────────

    public function shiftTemplate(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class, 'shift_template_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

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
            $format = substr_count($this->start_time, ':') === 2 ? 'H:i:s' : 'H:i';

            return Carbon::createFromFormat($format, $this->start_time)
                ->addMinutes($totalMinutes)
                ->format('H:i');
        });
    }
}
