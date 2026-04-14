<?php

namespace App\Containers\AppSection\Order\Tasks;

use App\Containers\AppSection\Order\Models\OrderSummary;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class GetOrderSummaryTask extends ParentTask
{
    /**
     * Get order summaries (total + per-line).
     *
     * Resolution logic:
     *   - date + shift → exact match
     *   - date only   → latest shift_number for that date
     *   - neither     → today + latest shift
     */
    public function run(?string $date = null, ?int $shiftNumber = null): array
    {
        $date = $date ?? now()->toDateString();

        // Resolve shift number: use provided, or find latest for this date
        if ($shiftNumber === null) {
            $shift = Shift::query()
                ->where('date', $date)
                ->latest('shift_number')
                ->first();

            $shiftNumber = $shift?->shift_number ?? 1;
        }

        $perLine = OrderSummary::query()
            ->forShift($date, $shiftNumber)
            ->perLine()
            ->get();

        // Compute total from per-line data (no separate DB row needed)
        $total = $perLine->isNotEmpty() ? [
            'total'          => $perLine->sum('total'),
            'completed'      => $perLine->sum('completed'),
            'remaining'      => $perLine->sum('remaining'),
            'rush_completed' => $perLine->sum('rush_completed'),
            'rush_total'     => $perLine->sum('rush_total'),
            'progress'       => $perLine->sum('total') > 0
                ? round(($perLine->sum('completed') / $perLine->sum('total')) * 100, 1)
                : 0,
            'estimated_done' => $perLine->max('estimated_done'),
        ] : null;

        return [
            'date'         => $date,
            'shift_number' => $shiftNumber,
            'total'        => $total,
            'per_line'     => $perLine,
        ];
    }
}
