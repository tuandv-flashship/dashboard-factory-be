<?php

namespace App\Containers\AppSection\Quality\Models;

use App\Ship\Parents\Models\Model as ParentModel;

final class QualityRecord extends ParentModel
{
    protected $table = 'quality_records';

    protected $fillable = [
        'date', 'shift_number', 'pass_rate', 'inspected', 'passed', 'failed', 'avg_error_rate',
    ];

    protected $casts = [
        'date' => 'immutable_date',
        'shift_number' => 'integer',
        'pass_rate' => 'float',
        'inspected' => 'integer',
        'passed' => 'integer',
        'failed' => 'integer',
        'avg_error_rate' => 'float',
    ];

    /**
     * Get the current shift's quality record.
     */
    public static function current(): self|null
    {
        return self::query()
            ->where('date', now()->toDateString())
            ->latest('shift_number')
            ->first();
    }

    /**
     * Resolve by date + shift_number, or fallback to current.
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
}
