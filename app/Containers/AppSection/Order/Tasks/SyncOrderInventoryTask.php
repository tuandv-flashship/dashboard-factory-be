<?php

namespace App\Containers\AppSection\Order\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Tasks\GetAllTeamsInventoryTask;
use App\Containers\AppSection\FplatformData\Tasks\GetHotshotOrderInventoryTask;
use App\Containers\AppSection\Order\Models\OrderSummary;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Facades\Log;

/**
 * Sync order inventory (tồn đơn hàng) from Fplatform → order_summaries.
 *
 * Both DTF and DTG order inventory are read from the shared allInventory cache
 * (populated by GetAllTeamsInventoryTask via order_inventory.lines.dtf/dtg),
 * avoiding duplicate FPlatform queries.
 *
 * Called by SyncHourlyRecordsTask (primary) and SyncOrderInventoryJob (manual resync).
 *
 * Line mapping:
 *   - FLS: line='dtf' (DTF1-FLS) only
 *   - PD:  line='dtf' (DTF2-PD) + line='dtg' (DTG-PD)
 *
 * Note: line=null (total) is NOT written — FE computes totals itself.
 */
final class SyncOrderInventoryTask extends ParentTask
{
    public function __construct(
        private readonly GetAllTeamsInventoryTask $allTeamsInventoryTask,
        private readonly GetHotshotOrderInventoryTask $hotshotTask,
    ) {
    }

    public function run(?string $date = null): void
    {
        $today = $date ?? now()->toDateString();

        // Must have a shift assigned for today
        $shift = Shift::query()
            ->whereDate('date', $today)
            ->latest('shift_number')
            ->first();

        if (!$shift) {
            Log::info('[SyncOrderInventory] No shift for today — skipped.', [
                'date' => $today,
            ]);

            return;
        }

        $shiftNumber = $shift->shift_number;
        $factory = FactoryLine::current();

        // estimated_done = thời gian kết thúc ca của department muộn nhất
        $estimatedDone = $this->resolveEstimatedDone($shift);

        // ── Fetch inventory (cache hit if SyncHourlyRecords already ran) ──
        $allInventory = $this->allTeamsInventoryTask->run($today);
        $dtfResult = $allInventory['teams']['order_inventory'] ?? null;

        // Hotshot orders (DTF only — filters by MayHOTSHOT/MayHOTSHOTPD)
        $hotshotResult = $this->hotshotTask->run($today, $factory);

        // ── Upsert per-line rows ──────────────────────────
        $upserted = 0;

        if ($dtfResult) {
            // order_inventory contains combined totals + per-line breakdown.
            // We must use lines.dtf for the DTF line — NOT the combined total.
            $dtfLine = $dtfResult['lines']['dtf'] ?? null;

            if ($dtfLine) {
                $rushTotal     = $hotshotResult ? $hotshotResult['tong_don'] : 0;
                $rushCompleted = $hotshotResult ? $hotshotResult['da_lam'] : 0;

                $this->upsertLine(
                    $today, $shiftNumber,
                    'dtf', 'DTF',
                    $dtfLine['tong_don'], $dtfLine['da_lam'],
                    $rushTotal, $rushCompleted,
                    $estimatedDone,
                );
                $upserted++;
            }
        }

        // DTG: always present in order_inventory.lines.dtg for PD factory
        // (guaranteed by GetDailyInventoryAction::runOrderInventory)
        $dtgLine = $dtfResult['lines']['dtg'] ?? null;

        if ($dtgLine && $dtgLine['tong_don'] > 0) {
            $this->upsertLine(
                $today, $shiftNumber,
                'dtg', 'DTG',
                $dtgLine['tong_don'], $dtgLine['da_lam'],
                0, 0,
                $estimatedDone,
            );
            $upserted++;
        }

        if ($upserted === 0) {
            Log::info('[SyncOrderInventory] No fplatform data — skipped.', [
                'date' => $today, 'factory' => $factory->value,
            ]);

            return;
        }

        Log::info('[SyncOrderInventory] Synced.', [
            'date'    => $today,
            'shift'   => $shiftNumber,
            'factory' => $factory->value,
            'lines'   => $upserted,
        ]);
    }

    private function upsertLine(
        string $date,
        int $shiftNumber,
        string $line,
        string $label,
        int $tongViec,
        int $daLam,
        int $rushTotal,
        int $rushCompleted,
        string $estimatedDone,
    ): void {
        $completed = $daLam;
        $remaining = max(0, $tongViec - $daLam);
        $progress = $tongViec > 0
            ? round(($completed / $tongViec) * 100, 1)
            : 0;

        $values = [
            'line_label'     => $label,
            'total'          => $tongViec,
            'completed'      => $completed,
            'remaining'      => $remaining,
            'estimated_done' => $estimatedDone,
            'rush_completed' => $rushCompleted,
            'rush_total'     => $rushTotal,
            'progress'       => $progress,
        ];

        // Use whereDate for immutable_date cast compatibility (SQLite + MySQL)
        /** @var OrderSummary|null $existing */
        $existing = OrderSummary::whereDate('date', $date)
            ->where('shift_number', $shiftNumber)
            ->where('line', $line)
            ->first();

        if ($existing) {
            $existing->update($values);
        } else {
            OrderSummary::create(array_merge([
                'date'         => $date,
                'shift_number' => $shiftNumber,
                'line'         => $line,
            ], $values));
        }
    }

    /**
     * Resolve estimated_done = end_time của department kết thúc muộn nhất.
     *
     * Tính trực tiếp trong SQL: MAX(start_time + work_hours + meal_break)
     * thay vì load tất cả ShiftDetail rồi tính bằng PHP accessor.
     *
     * Kết quả: 1 query, 1 scalar — không load N rows vào memory.
     */
    private function resolveEstimatedDone(Shift $shift): string
    {
        try {
            $maxEndTime = ShiftDetail::where('shift_id', $shift->id)
                ->selectRaw("
                    MAX(
                        DATE_FORMAT(
                            ADDTIME(start_time, SEC_TO_TIME((work_hours * 3600) + (COALESCE(meal_break_minutes, 0) * 60))),
                            '%H:%i'
                        )
                    ) AS max_end_time
                ")
                ->value('max_end_time');

            if ($maxEndTime) {
                return $maxEndTime;
            }
        } catch (\Illuminate\Database\QueryException) {
            // MySQL-specific functions unavailable (e.g., SQLite in tests)
        }

        // Fallback: shift-level end_time
        return $shift->end_time
            ? substr($shift->end_time, 0, 5)
            : '--';
    }
}
