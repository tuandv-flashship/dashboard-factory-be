<?php

namespace App\Containers\AppSection\Shift\Models;

use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

final class Shift extends ParentModel
{
    protected $table = 'shifts';

    protected $fillable = [
        'date', 'shift_number', 'start_time', 'end_time',
        'supervisor', 'is_active', 'shift_template_id',
    ];

    protected $casts = [
        'date'         => 'immutable_date',
        'shift_number' => 'integer',
        'is_active'    => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────

    public function template(): BelongsTo
    {
        return $this->belongsTo(ShiftTemplate::class, 'shift_template_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ShiftDetail::class, 'shift_id')
            ->orderBy('department_id')
            ->orderBy('shift_number');
    }

    public function hourlyRecords(): HasMany
    {
        return $this->hasMany(HourlyRecord::class, 'shift_id');
    }

    // ── Static Helpers ───────────────────────────────────

    /**
     * Get the currently active shift for today.
     */
    public static function current(): self|null
    {
        return self::query()
            ->where('is_active', true)
            ->where('date', now()->toDateString())
            ->latest('shift_number')
            ->first();
    }

    /**
     * Resolve shift by date + shift_number, or fallback to current.
     */
    public static function resolve(?string $date = null, ?int $shiftNumber = null): self|null
    {
        if ($date && $shiftNumber) {
            return self::query()
                ->where('date', $date)
                ->where('shift_number', $shiftNumber)
                ->first();
        }

        if ($date) {
            return self::query()
                ->where('date', $date)
                ->latest('shift_number')
                ->first();
        }

        return self::current();
    }

    /**
     * Get all shifts for a specific date.
     */
    public static function forDate(string $date): \Illuminate\Database\Eloquent\Collection
    {
        return self::query()
            ->where('date', $date)
            ->orderBy('shift_number')
            ->get();
    }
    /**
     * Check if current time falls within this shift's start–end window.
     *
     * Adds a configurable buffer to capture data near shift boundaries.
     * Handles overnight shifts (end_time < start_time).
     *
     * @param int $beforeMinutes Buffer before shift start (default 5)
     * @param int $afterMinutes  Buffer after shift end (default 30)
     */
    public function isWithinTimeWindow(int $beforeMinutes = 5, int $afterMinutes = 30): bool
    {
        $now = now();
        $date = $this->date->toDateString();

        $shiftStart = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$this->start_time}");
        $shiftEnd   = Carbon::createFromFormat('Y-m-d H:i:s', "{$date} {$this->end_time}");

        // Handle overnight shifts (end_time < start_time, e.g. 22:00–06:00)
        if ($shiftEnd->lte($shiftStart)) {
            $shiftEnd->addDay();
        }

        $windowStart = $shiftStart->copy()->subMinutes($beforeMinutes);
        $windowEnd   = $shiftEnd->copy()->addMinutes($afterMinutes);

        return $now->between($windowStart, $windowEnd);
    }
}
