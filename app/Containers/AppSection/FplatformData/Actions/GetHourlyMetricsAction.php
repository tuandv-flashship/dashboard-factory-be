<?php

namespace App\Containers\AppSection\FplatformData\Actions;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Enums\HourlyMetricType;
use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Containers\AppSection\FplatformData\Enums\WorkType;
use App\Containers\AppSection\FplatformData\Tasks\GetHourlyDtgPickMetricsTask;
use App\Containers\AppSection\FplatformData\Tasks\GetHourlyDtgPrintMetricsTask;
use App\Containers\AppSection\FplatformData\Tasks\GetHourlyMockupMetricsTask;
use App\Containers\AppSection\FplatformData\Tasks\GetHourlyPackShipMetricsTask;
use App\Containers\AppSection\FplatformData\Tasks\GetHourlyUgsMetricsTask;
use App\Ship\Parents\Actions\Action as ParentAction;

/**
 * Dispatch hourly metrics queries to the correct Task based on team + metric type.
 *
 * Consolidates 14 query groups into a single dispatch point.
 * Each Task handles multiple metric variants internally.
 */
final class GetHourlyMetricsAction extends ParentAction
{
    public function __construct(
        private readonly GetHourlyUgsMetricsTask $ugsTask,
        private readonly GetHourlyMockupMetricsTask $mockupTask,
        private readonly GetHourlyPackShipMetricsTask $packShipTask,
        private readonly GetHourlyDtgPrintMetricsTask $dtgPrintTask,
        private readonly GetHourlyDtgPickMetricsTask $dtgPickTask,
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
    ): array {
        $factory = FactoryLine::current();

        $hours = match ($team) {
            // UGS-based teams (IN, CẮT, PICK)
            Team::In => $this->ugsTask->run($startShift, $endShift, $factory, WorkType::In, $metric),
            Team::Cat => $this->ugsTask->run($startShift, $endShift, $factory, WorkType::Cat, $metric),
            Team::Pick => $this->ugsTask->run($startShift, $endShift, $factory, WorkType::Pick, $metric),

            // Mockup
            Team::Mockup => $this->mockupTask->run($startShift, $endShift, $factory, $metric),

            // Pack & Ship
            Team::PackShip => $this->packShipTask->run($startShift, $endShift, $factory, $metric),

            // DTG
            Team::DtgPrint => $this->dtgPrintTask->run($startShift, $endShift, $metric),
            Team::DtgPick => $this->dtgPickTask->run($startShift, $endShift, $metric),

            default => throw new \InvalidArgumentException("Team {$team->value} does not support hourly metrics"),
        };

        return [
            'team'   => $team->value,
            'metric' => $metric->value,
            'hours'  => $hours,
        ];
    }
}
