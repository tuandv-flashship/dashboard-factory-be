<?php

namespace App\Containers\AppSection\FplatformData\Actions;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Containers\AppSection\FplatformData\Enums\WorkType;
use App\Containers\AppSection\FplatformData\Tasks\GetDailyInventoryTask;
use App\Containers\AppSection\FplatformData\Tasks\GetDtgOrderInventoryTask;
use App\Containers\AppSection\FplatformData\Tasks\GetDtgPickInventoryTask;
use App\Containers\AppSection\FplatformData\Tasks\GetDtgPrintInventoryTask;
use App\Containers\AppSection\FplatformData\Tasks\GetDtgPrintMachineSplitTask;
use App\Containers\AppSection\FplatformData\Tasks\GetHotshotInventoryTask;
use App\Containers\AppSection\FplatformData\Tasks\GetHotshotMockupInventoryTask;
use App\Containers\AppSection\FplatformData\Tasks\GetHotshotPackShipInventoryTask;
use App\Containers\AppSection\FplatformData\Tasks\GetMockupInventoryTask;
use App\Containers\AppSection\FplatformData\Tasks\GetOrderInventoryTask;
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
        private readonly GetOrderInventoryTask $orderInventoryTask,
        private readonly GetDtgPickInventoryTask $dtgPickInventoryTask,
        private readonly GetDtgPrintInventoryTask $dtgPrintInventoryTask,
        private readonly GetDtgPrintMachineSplitTask $dtgPrintMachineSplitTask,
        private readonly GetDtgOrderInventoryTask $dtgOrderInventoryTask,
        private readonly GetHotshotInventoryTask $hotshotInventoryTask,
        private readonly GetHotshotMockupInventoryTask $hotshotMockupInventoryTask,
        private readonly GetHotshotPackShipInventoryTask $hotshotPackShipInventoryTask,
    ) {
    }

    /**
     * Dispatch to the correct Task based on team type.
     */
    public function run(string $date, Team $team): ?array
    {
        $factoryLine = FactoryLine::current();

        return match ($team) {
            Team::Print => $this->dailyInventoryTask->run(
                Carbon::parse($date), $factoryLine, WorkType::In
            ),
            Team::Cut => $this->dailyInventoryTask->run(
                Carbon::parse($date), $factoryLine, WorkType::Cat
            ),
            Team::Pick          => $this->pickInventoryTask->run($date, $factoryLine),
            Team::Mockup        => $this->mockupInventoryTask->run($date, $factoryLine),
            Team::PackShip      => $this->packShipInventoryTask->run($date, $factoryLine),
            Team::OrderInventory => $this->runOrderInventory($date, $factoryLine),
            Team::PickDtg       => $this->dtgPickInventoryTask->run($date),
            Team::DtgPrint      => $this->dtgPrintInventoryTask->run($date),
            Team::DtgPrintSplit => $this->dtgPrintMachineSplitTask->run($date),

            // Hotshot teams
            Team::HotshotPrint   => $this->hotshotInventoryTask->run($date, $factoryLine, WorkType::In),
            Team::HotshotPick    => $this->hotshotInventoryTask->run($date, $factoryLine, WorkType::Pick),
            Team::HotshotCut     => $this->hotshotInventoryTask->run($date, $factoryLine, WorkType::Cat),
            Team::HotshotMockup  => $this->hotshotMockupInventoryTask->run($date, $factoryLine),
            Team::HotshotPackShip => $this->hotshotPackShipInventoryTask->run($date, $factoryLine),
        };
    }

    /**
     * Order inventory: return per-line breakdown (DTF + DTG for PD).
     *
     * Response includes:
     *  - lines.dtf  → DTF line (both FLS & PD)
     *  - lines.dtg  → DTG line (PD only)
     *  - tong_viec / da_lam → totals across all lines
     */
    private function runOrderInventory(string $date, FactoryLine $factoryLine): ?array
    {
        $dtfResult = $this->orderInventoryTask->run($date, $factoryLine);
        $dtgResult = $factoryLine === FactoryLine::PD
            ? $this->dtgOrderInventoryTask->run($date)
            : null;

        // Both null → no data
        if (!$dtfResult && !$dtgResult) {
            return null;
        }

        $zero = ['tong_viec' => 0, 'da_lam' => 0];
        $dtfLine = $dtfResult ? ['tong_viec' => $dtfResult['tong_viec'], 'da_lam' => $dtfResult['da_lam']] : $zero;
        $dtgLine = $dtgResult ? ['tong_viec' => $dtgResult['tong_viec'], 'da_lam' => $dtgResult['da_lam']] : $zero;

        $lines = ['dtf' => $dtfLine];
        if ($factoryLine === FactoryLine::PD) {
            $lines['dtg'] = $dtgLine;
        }

        return [
            'estimate_date' => $date,
            'lines'         => $lines,
            'tong_viec'     => $dtfLine['tong_viec'] + $dtgLine['tong_viec'],
            'da_lam'        => $dtfLine['da_lam'] + $dtgLine['da_lam'],
        ];
    }
}
