<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Ship\Parents\Tasks\Task as ParentTask;
use App\Ship\Supports\DepartmentScope;
use Illuminate\Support\Facades\DB;

final class GetIssueSummaryTask extends ParentTask
{
    /**
     * Return aggregated issue counts for the dashboard summary bar.
     *
     * Response: total, pending, resolved, resolved_percent
     */
    public function run(
        ?string $date = null,
        ?int $shift = null,
        ?int $departmentId = null,
        ?string $category = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
    ): array {
        // ── 1. Build hourly_record subquery ─────────────────────────
        $recordQuery = HourlyRecord::query()->select('id');

        if ($date && $shift) {
            $shiftModel = Shift::resolve($date, $shift);

            if (!$shiftModel) {
                return self::empty();
            }

            $recordQuery->where('shift_id', $shiftModel->id);
        } elseif ($date || $dateFrom || $dateTo) {
            $shiftSubquery = Shift::query()->select('id')
                ->when($date, fn ($q) => $q->where('date', $date))
                ->when(!$date && $dateFrom, fn ($q) => $q->where('date', '>=', $dateFrom))
                ->when(!$date && $dateTo, fn ($q) => $q->where('date', '<=', $dateTo))
                ->when($shift, fn ($q) => $q->where('shift_number', $shift));

            $recordQuery->whereIn('shift_id', $shiftSubquery);
        } else {
            $shiftModel = Shift::current();

            if (!$shiftModel) {
                return self::empty();
            }

            $recordQuery->where('shift_id', $shiftModel->id);
        }

        if ($departmentId) {
            $recordQuery->where('department_id', $departmentId);
        }

        // Apply department scope
        DepartmentScope::applyToQuery($recordQuery, auth()->user(), 'hourly-issues.index');

        // ── 2. Aggregate in a single query ──────────────────────────
        $query = HourlyIssue::whereIn('hourly_record_id', $recordQuery);

        if ($category) {
            $query->where('category', $category);
        }

        $stats = $query->select([
            DB::raw('COUNT(*) as total'),
            DB::raw('SUM(CASE WHEN resolved_at IS NULL THEN 1 ELSE 0 END) as pending'),
            DB::raw('SUM(CASE WHEN resolved_at IS NOT NULL THEN 1 ELSE 0 END) as resolved'),
        ])->first();

        $total    = (int) $stats->total;
        $pending  = (int) $stats->pending;
        $resolved = (int) $stats->resolved;

        return [
            'total'            => $total,
            'pending'          => $pending,
            'resolved'         => $resolved,
            'resolved_percent' => $total > 0
                ? round($resolved / $total * 100, 1)
                : 0,
        ];
    }

    private static function empty(): array
    {
        return [
            'total'            => 0,
            'pending'          => 0,
            'resolved'         => 0,
            'resolved_percent' => 0,
        ];
    }
}
