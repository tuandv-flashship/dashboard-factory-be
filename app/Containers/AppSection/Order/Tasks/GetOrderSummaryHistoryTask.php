<?php

namespace App\Containers\AppSection\Order\Tasks;

use App\Containers\AppSection\Order\Models\OrderSummary;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class GetOrderSummaryHistoryTask extends ParentTask
{
    /**
     * Get order summary history for the last N days.
     *
     * When line is null, aggregates totals across all lines per date.
     * When line is specified (dtf/dtg), returns that line's data per date.
     *
     * Only the latest shift_number per date is used (correlated subquery).
     * Returns a collection of daily summaries ordered by date DESC.
     */
    public function run(int $days = 10, ?string $line = null): Collection
    {
        $startDate = now()->subDays($days - 1)->toDateString();
        $endDate = now()->toDateString();

        // Use a correlated subquery to pick only the latest shift per date.
        // This avoids the flat-set bug where a shift_number from one date
        // incorrectly includes rows from another date.
        $query = OrderSummary::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->whereNotNull('line')
            ->whereRaw('shift_number = (
                SELECT MAX(os2.shift_number)
                FROM order_summaries os2
                WHERE os2.date = order_summaries.date
                  AND os2.line IS NOT NULL
            )');

        if ($line !== null) {
            $query->where('line', $line);
        }

        $rows = $query->get();

        // Group by date and aggregate
        return $rows->groupBy(fn ($row) => $row->date->toDateString())
            ->map(function (Collection $dayRows, string $date) {
                return [
                    'date'      => $date,
                    'total'     => $dayRows->sum('total'),
                    'completed' => $dayRows->sum('completed'),
                    'remaining' => $dayRows->sum('remaining'),
                ];
            })
            ->sortByDesc('date')
            ->values();
    }
}
