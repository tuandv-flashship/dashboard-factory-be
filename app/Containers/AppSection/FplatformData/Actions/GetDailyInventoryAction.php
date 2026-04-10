<?php

namespace App\Containers\AppSection\FplatformData\Actions;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Containers\AppSection\FplatformData\Enums\WorkType;
use App\Containers\AppSection\FplatformData\Tasks\GetDailyInventoryTask;
use App\Containers\AppSection\FplatformData\Tasks\GetDtgPickInventoryTask;
use App\Containers\AppSection\FplatformData\Tasks\GetDtgPrintInventoryTask;
use App\Containers\AppSection\FplatformData\Tasks\GetDtgPrintMachineSplitTask;
use App\Containers\AppSection\FplatformData\Tasks\GetMockupInventoryTask;
use App\Containers\AppSection\FplatformData\Tasks\GetPackShipInventoryTask;
use App\Containers\AppSection\FplatformData\Tasks\GetPickInventoryTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Carbon;

final class GetDailyInventoryAction extends ParentAction
{
    public function __construct(
        private readonly GetDailyInventoryTask $dailyInventoryTask,
        private readonly GetPickInventoryTask $pickInventoryTask,
        private readonly GetMockupInventoryTask $mockupInventoryTask,
        private readonly GetPackShipInventoryTask $packShipInventoryTask,
        private readonly GetDtgPickInventoryTask $dtgPickInventoryTask,
        private readonly GetDtgPrintInventoryTask $dtgPrintInventoryTask,
        private readonly GetDtgPrintMachineSplitTask $dtgPrintMachineSplitTask,
    ) {
    }

    /**
     * Dispatch to the correct Task based on team type.
     */
    public function run(string $date, Team $team): ?array
    {
        $factoryLine = FactoryLine::current();

        return match ($team) {
            Team::In  => $this->dailyInventoryTask->run(
                Carbon::parse($date), $factoryLine, WorkType::In
            ),
            Team::Cat => $this->dailyInventoryTask->run(
                Carbon::parse($date), $factoryLine, WorkType::Cat
            ),
            Team::Pick          => $this->pickInventoryTask->run($date, $factoryLine),
            Team::Mockup        => $this->mockupInventoryTask->run($date, $factoryLine),
            Team::PackShip      => $this->packShipInventoryTask->run($date, $factoryLine),
            Team::DtgPick       => $this->dtgPickInventoryTask->run($date),
            Team::DtgPrint      => $this->dtgPrintInventoryTask->run($date),
            Team::DtgPrintSplit => $this->dtgPrintMachineSplitTask->run($date),
        };
    }
}
