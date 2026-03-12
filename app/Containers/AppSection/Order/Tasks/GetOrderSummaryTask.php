<?php

namespace App\Containers\AppSection\Order\Tasks;

use App\Containers\AppSection\Order\Models\OrderSummary;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class GetOrderSummaryTask extends ParentTask
{
    /**
     * Get order summaries (total + per-line).
     * Defaults to today + latest shift if no params given.
     */
    public function run(?string $date = null, ?int $shiftNumber = null): array
    {
        $date = $date ?? now()->toDateString();
        $shiftNumber = $shiftNumber ?? 1;

        $total = OrderSummary::query()
            ->forShift($date, $shiftNumber)
            ->total()
            ->first();

        $perLine = OrderSummary::query()
            ->forShift($date, $shiftNumber)
            ->perLine()
            ->get();

        return [
            'total' => $total,
            'per_line' => $perLine,
        ];
    }
}
