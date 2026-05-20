<?php

namespace App\Containers\AppSection\FplatformData\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Services\CutHourlyImageAllocator;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Carbon;

/**
 * Get CUT hourly productivity using time-proportional image allocation.
 *
 * Replaces the old SUM(total_file) GROUP BY date_hour approach
 * with proportional distribution based on actual working time per block.
 *
 * @see docs/tinh-so-hinh-thuc-te-trong-block-gio.md
 */
final class GetCutHourlyProductivityTask extends ParentTask
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
     * @return array<int, array{date_hour: string, value: int}>
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

        $hourMap = $this->allocator->allocate($logs, $shiftDate, $shiftStartTime, $breaks);

        // Convert [hourKey => value] map → [{date_hour, value}] array
        return array_map(
            fn (int $value, string $key) => ['date_hour' => $key, 'value' => $value],
            $hourMap,
            array_keys($hourMap),
        );
    }
}
