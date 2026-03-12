<?php

namespace App\Containers\AppSection\Order\Models;

use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Builder;

final class OrderSummary extends ParentModel
{
    protected $table = 'order_summaries';

    protected $fillable = [
        'date', 'shift_number', 'line', 'line_label',
        'total', 'completed', 'remaining', 'estimated_done',
        'rush_completed', 'rush_total', 'progress',
    ];

    protected $casts = [
        'date' => 'immutable_date',
        'shift_number' => 'integer',
        'total' => 'integer',
        'completed' => 'integer',
        'remaining' => 'integer',
        'rush_completed' => 'integer',
        'rush_total' => 'integer',
        'progress' => 'float',
    ];

    /**
     * Scope: get total order summary (line=null).
     */
    public function scopeTotal(Builder $query): Builder
    {
        return $query->whereNull('line');
    }

    /**
     * Scope: get per-line order summaries.
     */
    public function scopePerLine(Builder $query): Builder
    {
        return $query->whereNotNull('line')->orderByRaw("CASE line WHEN 'dtf1' THEN 1 WHEN 'dtf2' THEN 2 WHEN 'dtg' THEN 3 ELSE 4 END");
    }

    /**
     * Scope: filter by date and shift.
     */
    public function scopeForShift(Builder $query, string $date, int $shiftNumber): Builder
    {
        return $query->where('date', $date)->where('shift_number', $shiftNumber);
    }
}
