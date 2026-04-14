<?php

namespace App\Containers\AppSection\Order\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Tasks\GetDtgOrderInventoryTask;
use App\Containers\AppSection\FplatformData\Tasks\GetHotshotOrderInventoryTask;
use App\Containers\AppSection\FplatformData\Tasks\GetOrderInventoryTask;
use App\Containers\AppSection\Order\Models\OrderSummary;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Facades\Log;

/**
 * Sync order inventory (tồn đơn hàng) from Fplatform → order_summaries.
 *
 * Fetches per-line data (DTF + DTG) and hotshot counts from fplatform,
 * then upserts into the local order_summaries table.
 * Called by SyncOrderInventoryJob.
 *
 * Line mapping:
 *   - FLS: line='dtf' (DTF1-FLS)
 *   - PD:  line='dtf' (DTF2-PD) + line='dtg' (DTG-PD)
 *
 * Note: line=null (total) is NOT written — FE computes totals itself.
 */
final class SyncOrderInventoryTask extends ParentTask
{
    public function __construct(
        private readonly GetOrderInventoryTask $dtfTask,
        private readonly GetDtgOrderInventoryTask $dtgTask,
        private readonly GetHotshotOrderInventoryTask $hotshotTask,
    ) {
    }

    public function run(): void
    {
        $today = now()->toDateString();

        // Must have a shift assigned for today
        $shift = Shift::query()
            ->where('date', $today)
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

        // ── Fetch from fplatform ──────────────────────────
        $dtfResult = $this->dtfTask->run($today, $factory);
        $dtgResult = $factory === FactoryLine::PD
            ? $this->dtgTask->run($today)
            : null;

        // Hotshot (DTF only — filters by MayHOTSHOT/MayHOTSHOTPD)
        $hotshotResult = $this->hotshotTask->run($today, $factory);

        // ── Upsert per-line rows ──────────────────────────
        $upserted = 0;

        if ($dtfResult) {
            $rushTotal = $hotshotResult ? $hotshotResult['ton_dau'] : 0;
            $rushCompleted = $hotshotResult
                ? max(0, $hotshotResult['ton_dau'] - $hotshotResult['ton_cuoi'])
                : 0;

            $this->upsertLine(
                $today, $shiftNumber,
                'dtf', 'DTF',
                $dtfResult['ton_dau'], $dtfResult['ton_cuoi'],
                $rushTotal, $rushCompleted,
                $estimatedDone,
            );
            $upserted++;
        }

        if ($dtgResult) {
            $this->upsertLine(
                $today, $shiftNumber,
                'dtg', 'DTG',
                $dtgResult['ton_dau'], $dtgResult['ton_cuoi'],
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
        int $tonDau,
        int $tonCuoi,
        int $rushTotal,
        int $rushCompleted,
        string $estimatedDone,
    ): void {
        $completed = max(0, $tonDau - $tonCuoi);
        $progress = $tonDau > 0
            ? round(($completed / $tonDau) * 100, 1)
            : 0;

        OrderSummary::updateOrCreate(
            [
                'date'         => $date,
                'shift_number' => $shiftNumber,
                'line'         => $line,
            ],
            [
                'line_label'     => $label,
                'total'          => $tonDau,
                'completed'      => $completed,
                'remaining'      => $tonCuoi,
                'estimated_done' => $estimatedDone,
                'rush_completed' => $rushCompleted,
                'rush_total'     => $rushTotal,
                'progress'       => $progress,
            ],
        );
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

        // Fallback: shift-level end_time
        return $shift->end_time
            ? substr($shift->end_time, 0, 5)
            : '--';
    }
}
