<?php

namespace App\Containers\AppSection\FplatformData\Actions;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Enums\HourlyMetricType;
use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Containers\AppSection\FplatformData\Enums\WorkType;
use App\Containers\AppSection\FplatformData\Services\CutHourlyImageAllocator;
use App\Containers\AppSection\FplatformData\Tasks\GetCutHourlyProductivityTask;
use App\Containers\AppSection\FplatformData\Tasks\GetCutStaffHourlyProductivityTask;
use App\Containers\AppSection\FplatformData\Tasks\GetHourlyDtgPickMetricsTask;
use App\Containers\AppSection\FplatformData\Tasks\GetHourlyDtgPrintMetricsTask;
use App\Containers\AppSection\FplatformData\Tasks\GetHourlyMockupMetricsTask;
use App\Containers\AppSection\FplatformData\Tasks\GetHourlyPackShipMetricsTask;
use App\Containers\AppSection\FplatformData\Tasks\GetHourlyUgsMetricsTask;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Carbon;

/**
 * Dispatch hourly metrics queries to the correct Task based on team + metric type.
 *
 * Consolidates 14 query groups into a single dispatch point.
 * Each Task handles multiple metric variants internally.
 *
 * CUT team Productivity/StaffProductivity use time-proportional image
 * allocation instead of simple SUM(total_file) per scan hour.
 */
final class GetHourlyMetricsAction extends ParentAction
{
    public function __construct(
        private readonly GetHourlyUgsMetricsTask $ugsTask,
        private readonly GetHourlyMockupMetricsTask $mockupTask,
        private readonly GetHourlyPackShipMetricsTask $packShipTask,
        private readonly GetHourlyDtgPrintMetricsTask $dtgPrintTask,
        private readonly GetHourlyDtgPickMetricsTask $dtgPickTask,
        private readonly GetCutHourlyProductivityTask $cutProductivityTask,
        private readonly GetCutStaffHourlyProductivityTask $cutStaffProductivityTask,
    ) {
    }

    /**
     * @return array{team: string, metric: string, hours: array}
     */
    public function run(
        Team $team,
        HourlyMetricType $metric,
        string $startShift,
        string $endShift,
        ?FactoryLine $factory = null,
    ): array {
        $factory = $factory ?? FactoryLine::current();

        $hours = match ($team) {
            // UGS-based teams (Print, Pick) — unchanged
            Team::Print => $this->ugsTask->run($startShift, $endShift, $factory, WorkType::In, $metric),
            Team::Pick => $this->ugsTask->run($startShift, $endShift, $factory, WorkType::Pick, $metric),
            Team::PickDtf2 => $this->ugsTask->run($startShift, $endShift, FactoryLine::FLS, WorkType::Pick, $metric),

            // CUT — time-proportional for Productivity/StaffProductivity, legacy for StaffCount
            Team::Cut => $this->resolveCutMetric($metric, $startShift, $endShift, $factory),

            // Mockup
            Team::Mockup => $this->mockupTask->run($startShift, $endShift, $factory, $metric),

            // Pack & Ship
            Team::PackShip => $this->packShipTask->run($startShift, $endShift, $factory, $metric),

            // DTG
            Team::DtgPrint => $this->dtgPrintTask->run($startShift, $endShift, $metric),
            Team::PickDtg => $this->dtgPickTask->run($startShift, $endShift, $metric),

            default => throw new \InvalidArgumentException("Team {$team->value} does not support hourly metrics"),
        };

        return [
            'team'   => $team->value,
            'metric' => $metric->value,
            'hours'  => $hours,
        ];
    }

    /**
     * Route CUT metrics to the appropriate task.
     *
     * Productivity + StaffProductivity → new time-proportional allocation.
     * StaffCount → legacy UGS task (counts distinct users per hour).
     */
    private function resolveCutMetric(
        HourlyMetricType $metric,
        string $startShift,
        string $endShift,
        FactoryLine $factory,
    ): array {
        // StaffCount keeps legacy logic — counts distinct user_id per hour
        if ($metric === HourlyMetricType::StaffCount) {
            return $this->ugsTask->run($startShift, $endShift, $factory, WorkType::Cat, $metric);
        }

        // Productivity + StaffProductivity → new time-proportional allocation
        [$shiftDate, $shiftStartTime, $breaks] = $this->lookupCutShiftBreaks($startShift);

        if ($metric === HourlyMetricType::Productivity) {
            return $this->cutProductivityTask->run(
                $startShift, $endShift, $factory,
                $shiftStartTime, $shiftDate, $breaks,
            );
        }

        // StaffProductivity
        return $this->cutStaffProductivityTask->run(
            $startShift, $endShift, $factory,
            $shiftStartTime, $shiftDate, $breaks,
        );
    }

    /**
     * Lookup the CUT department's shift_details to extract break schedule
     * and shift start time. Used by API endpoint which doesn't have
     * ShiftDetail context (unlike SyncDepartmentHourlyJob).
     *
     * Factory line is determined by deployment (FACTORY env), not stored
     * in the shifts table, so no factory filter is needed here.
     *
     * @return array{0: string, 1: string, 2: array} [shiftDate, shiftStartTime, breaks]
     */
    private function lookupCutShiftBreaks(string $startShift): array
    {
        $startCarbon = Carbon::parse($startShift);
        $shiftDate = $startCarbon->toDateString();

        $detail = ShiftDetail::whereHas('department', fn ($q) => $q->where('code', 'cut'))
            ->whereHas('shift', fn ($q) => $q->where('date', $shiftDate))
            ->first();

        if (!$detail) {
            // Fallback: use startShift time as shift start, no breaks
            return [$shiftDate, $startCarbon->format('H:i:s'), []];
        }

        $breaks = CutHourlyImageAllocator::extractBreaks($detail, $shiftDate);

        return [$shiftDate, $detail->start_time, $breaks];
    }
}

