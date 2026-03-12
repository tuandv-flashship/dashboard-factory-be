<?php

namespace App\Containers\AppSection\Production\Models;

use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Shift extends ParentModel
{
    protected $table = 'shifts';

    protected $fillable = [
        'date', 'shift_number', 'start_time', 'end_time', 'supervisor', 'is_active',
    ];

    protected $casts = [
        'date' => 'immutable_date',
        'shift_number' => 'integer',
        'is_active' => 'boolean',
    ];

    public function hourlyRecords(): HasMany
    {
        return $this->hasMany(HourlyRecord::class, 'shift_id');
    }

    public function pickHourlyRecords(): HasMany
    {
        return $this->hasMany(PickHourlyRecord::class, 'shift_id');
    }

    /**
     * Get the currently active shift.
     */
    public static function current(): self|null
    {
        return self::query()
            ->where('is_active', true)
            ->latest('date')
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
            // If only date given, return the latest shift of that date
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
}
