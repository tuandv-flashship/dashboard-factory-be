<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Services\CutHourlyImageAllocator;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Carbon;

/**
 * Get CUT staff hourly productivity using time-proportional image allocation.
 *
 * Same algorithm as GetCutHourlyProductivityTask but returns results
 * grouped by username, compatible with StaffProductivity metric format.
 *
 * @see docs/tinh-so-hinh-thuc-te-trong-block-gio.md
 */
final class GetCutStaffHourlyProductivityTask extends ParentTask
{
    public function __construct(
        private readonly GetLogFileCutTask $logFileCutTask,
        private readonly CutHourlyImageAllocator $allocator,
    ) {
    }

    /**
     * @param  string $startShift     "Y-m-d H:i:s" (US/Central)
     * @param  string $endShift       "Y-m-d H:i:s" (US/Central)
     * @param  FactoryLine $factory
     * @param  string $shiftStartTime "HH:mm:ss" — CUT dept shift start time
     * @param  string $shiftDate      "Y-m-d"
     * @param  array  $breaks         [{start: Carbon, end: Carbon}, ...]
     * @return array<int, array{date_hour: string, username: string, value: int}>
     */
    public function run(
        string $startShift,
        string $endShift,
        FactoryLine $factory,
        string $shiftStartTime,
        string $shiftDate,
        array $breaks,
    ): array {
        $logs = $this->logFileCutTask->run($startShift, $endShift, $factory);

        return $this->allocator->allocatePerUser($logs, $shiftDate, $shiftStartTime, $breaks);
    }
}
