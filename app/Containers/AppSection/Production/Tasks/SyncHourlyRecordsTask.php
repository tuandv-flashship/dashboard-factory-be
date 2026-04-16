<?php

namespace App\Containers\AppSection\Production\Tasks;

use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Containers\AppSection\FplatformData\Tasks\GetAllTeamsInventoryTask;
use App\Containers\AppSection\Production\Jobs\SyncDepartmentHourlyJob;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Bus\Batch;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;

/**
 * Dispatch parallel sync jobs for each department's hourly records.
 *
 * Fetches shared data (inventory) once, then dispatches a
 * SyncDepartmentHourlyJob per department via Bus::batch().
 * All departments sync concurrently via queue workers.
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
    ) {
    }

    /**
     * @return array{synced: int, shift: Shift|null, message: string}
     */
    public function run(?string $date = null, ?int $shiftNumber = null): array
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

        // Fetch inventory once — shared across all department jobs
        $allInventory = $this->allTeamsInventoryTask->run($shiftDate);

        // Build per-department jobs
        $jobs = [];
        foreach ($shiftDetails as $detail) {
            $dept = $detail->department;
            if (!$dept) {
                continue;
            }

            $team = self::DEPT_TEAM_MAP[$dept->code] ?? null;
            if (!$team) {
                continue;
            }

            $jobs[] = new SyncDepartmentHourlyJob(
                $shift->id,
                $detail->id,
                $allInventory,
            );
        }

        if (empty($jobs)) {
            return ['synced' => 0, 'shift' => $shift, 'message' => 'No departments to sync.'];
        }

        // Dispatch all departments in parallel via Bus::batch()
        $deptCount = count($jobs);
        Bus::batch($jobs)
            ->name("sync-hourly:{$shiftDate}:shift-{$shift->shift_number}")
            ->onQueue('sync')
            ->allowFailures()
            ->then(function (Batch $batch) use ($shiftDate, $shift) {
                Log::info("[SyncHourlyRecords] Batch completed for {$shiftDate} shift {$shift->shift_number}.", [
                    'total'  => $batch->totalJobs,
                    'failed' => $batch->failedJobs,
                ]);
            })
            ->catch(function (Batch $batch, \Throwable $e) {
                Log::warning('[SyncHourlyRecords] Batch had failures.', [
                    'error' => $e->getMessage(),
                ]);
            })
            ->dispatch();

        $msg = "Dispatched {$deptCount} department sync jobs for {$shiftDate} shift {$shift->shift_number}.";
        Log::info("[SyncHourlyRecords] {$msg}");

        return ['synced' => $deptCount, 'shift' => $shift, 'message' => $msg];
    }
}
