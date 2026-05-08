<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Containers\AppSection\FplatformData\Tasks\GetAllTeamsInventoryTask;
use App\Containers\AppSection\Order\Tasks\SyncOrderInventoryTask;
use App\Containers\AppSection\Production\Jobs\SyncDepartmentHourlyJob;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * 2-stage parallel pipeline for syncing hourly production data.
 *
 * Stage 1: Dispatch FetchTeamInventoryJob × N via Bus::batch()
 *          → each job queries 1 FPlatform team and caches individually.
 *          → 10+ workers execute in parallel → ~2-3s vs ~15s sequential.
 *
 * Stage 2 (then callback after Stage 1 completes):
 *          → Assemble allInventory from per-team caches
 *          → Dispatch SyncDepartmentHourlyJob × N via Bus::batch()
 *          → Run SyncOrderInventoryTask synchronously
 *
 * Called by SyncHourlyRecordsJob (cron) and ResyncHourlyRecordsController/Command.
 */
final class SyncHourlyRecordsTask extends ParentTask
{
    private const DEPT_TEAM_MAP = [
        'print'     => Team::Print,
        'cut'       => Team::Cut,
        'pick'      => Team::Pick,
        'mockup'    => Team::Mockup,
        'pack_ship' => Team::PackShip,
        'pick_dtg'  => Team::PickDtg,
        'dtg_print' => Team::DtgPrint,
    ];

    public function __construct(
        private readonly GetAllTeamsInventoryTask $allTeamsInventoryTask,
        private readonly SyncOrderInventoryTask $syncOrderInventoryTask,
    ) {
    }

    /**
     * @param int|null $shiftDetailId  When provided, only the matching ShiftDetail is synced.
     *                                   Useful for targeted resync after a single dept's
     *                                   work_hours / break schedule is updated.
     *
     * @return array{synced: int, shift: Shift|null, message: string}
     */
    public function run(?string $date = null, ?int $shiftNumber = null, ?int $shiftDetailId = null): array
    {
        $shift = ($date || $shiftNumber)
            ? Shift::resolve($date, $shiftNumber)
            : Shift::current();

        if (!$shift) {
            $msg = '[SyncHourlyRecords] No shift found — skipped.';
            Log::info($msg, ['date' => $date ?? now()->toDateString(), 'shift' => $shiftNumber]);

            return ['synced' => 0, 'shift' => null, 'message' => 'No shift found.'];
        }

        $shiftDate = $shift->date->toDateString();

        /** @var \Illuminate\Database\Eloquent\Collection<int, ShiftDetail> $shiftDetails */
        $shiftDetails = ShiftDetail::with('department')
            ->where('shift_id', $shift->id)
            ->get();

        if ($shiftDetails->isEmpty()) {
            return ['synced' => 0, 'shift' => $shift, 'message' => 'No shift details found.'];
        }

        // Build dept job data for Stage 2 callback (serializable)
        $deptJobData = $this->buildDeptJobData($shift, $shiftDetails, $shiftDetailId);
        $deptCount = count($deptJobData);

        if ($deptCount === 0) {
            return ['synced' => 0, 'shift' => $shift, 'message' => 'No departments to sync.'];
        }

        // ── Stage 1: Parallel inventory fetch ────────────
        $shiftId = $shift->id;
        $shiftNum = $shift->shift_number;

        $this->allTeamsInventoryTask
            ->dispatchParallelFetch($shiftDate)
            ->name("pipeline:{$shiftDate}:shift-{$shiftNum}:stage-1-fetch")
            ->then(function (Batch $batch) use ($shiftDate, $shiftId, $shiftNum, $deptJobData) {
                // ── Stage 2: Assemble + dispatch dept sync ────
                Log::info("[SyncHourlyRecords] Stage 1 done — assembling inventory.", [
                    'date'   => $shiftDate,
                    'fetched' => $batch->totalJobs,
                    'failed'  => $batch->failedJobs,
                ]);

                $allInventory = app(GetAllTeamsInventoryTask::class)->assembleFromCache($shiftDate);

                if (!$allInventory) {
                    Log::error('[SyncHourlyRecords] Stage 2 aborted — could not assemble inventory.', [
                        'date' => $shiftDate,
                    ]);

                    return;
                }

                // Dispatch dept sync jobs in parallel
                $deptJobs = array_map(
                    fn (array $d) => new SyncDepartmentHourlyJob($d['shift_id'], $d['detail_id'], $allInventory),
                    $deptJobData,
                );

                Bus::batch($deptJobs)
                    ->name("pipeline:{$shiftDate}:shift-{$shiftNum}:stage-2-sync")
                    ->onQueue('sync')
                    ->allowFailures()
                    ->then(function (Batch $b) use ($shiftDate, $shiftNum) {
                        Log::info("[SyncHourlyRecords] Stage 2 dept sync done for {$shiftDate} shift {$shiftNum}.", [
                            'total'  => $b->totalJobs,
                            'failed' => $b->failedJobs,
                        ]);
                    })
                    ->dispatch();

                // Order sync (uses cached allInventory — fast)
                try {
                    $this->syncOrderInventoryTask->run($shiftDate);
                } catch (\Throwable $e) {
                    Log::warning('[SyncHourlyRecords] Order inventory sync failed', [
                        'date'  => $shiftDate,
                        'error' => $e->getMessage(),
                    ]);
                }
            })
            ->catch(function (Batch $batch, \Throwable $e) use ($shiftDate) {
                Log::error('[SyncHourlyRecords] Stage 1 had failures.', [
                    'date'  => $shiftDate,
                    'error' => $e->getMessage(),
                ]);
            })
            ->dispatch();

        $target = $shiftDetailId ? "shift_detail #{$shiftDetailId}" : "{$deptCount} depts";
        $msg = "Pipeline dispatched: {$target} for {$shiftDate} shift {$shiftNum}.";
        Log::info("[SyncHourlyRecords] {$msg}");

        return ['synced' => $deptCount, 'shift' => $shift, 'message' => $msg];
    }

    /**
     * Build serializable dept job data (shift_id + detail_id arrays).
     * Used in the then() callback to create SyncDepartmentHourlyJob instances.
     *
     * During scheduled sync ($shiftDetailId is null), departments whose
     * working hours have already ended are skipped to avoid unnecessary
     * FPlatform API calls. Manual/targeted resync always processes.
     *
     * @param int|null $shiftDetailId  When set, only that ShiftDetail is included.
     * @return array<int, array{shift_id: int, detail_id: int}>
     */
    private function buildDeptJobData(Shift $shift, $shiftDetails, ?int $shiftDetailId = null): array
    {
        $data = [];
        $now  = now();
        $shiftDate = $shift->date->toDateString();

        foreach ($shiftDetails as $detail) {
            // ── Targeted resync: skip all other departments ──
            if ($shiftDetailId !== null && $detail->id !== $shiftDetailId) {
                continue;
            }

            $dept = $detail->department;
            if (!$dept) {
                continue;
            }

            $team = self::DEPT_TEAM_MAP[$dept->code] ?? null;
            if (!$team) {
                continue;
            }

            // ── Scheduled sync: skip departments whose shift has ended ──
            // Manual/targeted resync ($shiftDetailId !== null) always processes.
            if ($shiftDetailId === null && $detail->end_time !== null) {
                $deptEndAt = \Illuminate\Support\Carbon::createFromFormat(
                    'Y-m-d H:i',
                    "{$shiftDate} {$detail->end_time}",
                );

                // Handle overnight departments (end_time wraps past midnight)
                $deptStartAt = \Illuminate\Support\Carbon::createFromFormat(
                    'Y-m-d H:i:s',
                    "{$shiftDate} {$detail->start_time}",
                );
                if ($deptEndAt->lt($deptStartAt)) {
                    $deptEndAt->addDay();
                }

                if ($now->gte($deptEndAt)) {
                    Log::debug("[SyncHourlyRecords] Skipping {$dept->code} — ended at {$deptEndAt->format('H:i')}.");
                    continue;
                }
            }

            $data[] = [
                'shift_id'  => $shift->id,
                'detail_id' => $detail->id,
            ];
        }

        return $data;
    }
}
