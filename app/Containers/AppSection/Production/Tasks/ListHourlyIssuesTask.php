<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Ship\Parents\Tasks\Task as ParentTask;
use App\Ship\Supports\DepartmentScope;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class ListHourlyIssuesTask extends ParentTask
{
    /**
     * Return all HourlyIssues matching the given filters.
     *
     * Filter priority:
     *  1. date + shift  → single shift lookup
     *  2. date_from / date_to → range across multiple shifts
     *  3. fallback → current shift
     *
     * Additional filters: department_id, category, resolved status.
     *
     * @return LengthAwarePaginator<HourlyIssue>
     */
    public function run(
        ?string $date = null,
        ?int $shift = null,
        ?int $departmentId = null,
        ?string $category = null,
        ?bool $resolved = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $perPage = 20,
    ): LengthAwarePaginator {
        // ── 1. Build hourly_record subquery ─────────────────────────
        $recordQuery = HourlyRecord::query()->select('id');

        if ($date && $shift) {
            // Exact: single shift
            $shiftModel = Shift::resolve($date, $shift);

            if (!$shiftModel) {
                return HourlyIssue::query()->whereRaw('1 = 0')->paginate($perPage);
            }

            $recordQuery->where('shift_id', $shiftModel->id);
        } elseif ($date || $dateFrom || $dateTo) {
            // Date or date-range: resolve shift IDs via subquery
            $shiftSubquery = Shift::query()->select('id')
                ->when($date, fn ($q) => $q->where('date', $date))
                ->when(!$date && $dateFrom, fn ($q) => $q->where('date', '>=', $dateFrom))
                ->when(!$date && $dateTo, fn ($q) => $q->where('date', '<=', $dateTo))
                ->when($shift, fn ($q) => $q->where('shift_number', $shift));

            $recordQuery->whereIn('shift_id', $shiftSubquery);
        } else {
            // Fallback: current shift
            $shiftModel = Shift::current();

            if (!$shiftModel) {
                return HourlyIssue::query()->whereRaw('1 = 0')->paginate($perPage);
            }

            $recordQuery->where('shift_id', $shiftModel->id);
        }

        // Filter by department
        if ($departmentId) {
            $recordQuery->where('department_id', $departmentId);
        }

        // Apply department scope — limit to user's allowed departments
        DepartmentScope::applyToQuery($recordQuery, auth()->user(), 'hourly-issues.index');

        // ── 2. Query issues (single DB round-trip via subquery) ─────
        $query = HourlyIssue::whereIn('hourly_record_id', $recordQuery)
            ->with([
                'hourlyRecord',
                'hourlyRecord.department',
            ]);

        // Filter by category
        if ($category) {
            $query->where('category', $category);
        }

        // Filter by resolved status
        if ($resolved === true) {
            $query->whereNotNull('resolved_at');
        } elseif ($resolved === false) {
            $query->whereNull('resolved_at');
        }

        return $query->orderBy('hourly_record_id')->paginate($perPage);
    }
}
