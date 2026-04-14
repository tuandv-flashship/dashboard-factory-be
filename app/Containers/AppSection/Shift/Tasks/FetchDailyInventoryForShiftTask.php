<?php

namespace App\Containers\AppSection\Shift\Tasks;

use App\Containers\AppSection\FplatformData\Actions\GetDailyInventoryAction;
use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Fetch tồn đầu ngày (day_start_inventory) from Fplatform for all departments.
 *
 * Maps department codes → Fplatform Team enum, queries each,
 * and returns a map of department_id → ton_dau.
 *
 * Graceful degradation: if Fplatform is down, returns 0 for affected departments.
 */
class FetchDailyInventoryForShiftTask extends ParentTask
{
    /**
     * Department code → Fplatform Team mapping.
     *
     * Keys match department.code values from DepartmentSeeder.
     * DTF teams use FactoryLine::current() internally (FLS or PD).
     * DTG teams don't need factory parameter.
     */
    private const DEPT_TEAM_MAP = [
        // DTF departments (both FLS and PD)
        'print'     => Team::Print,
        'pick'      => Team::Pick,
        'cut'       => Team::Cut,
        'mockup'    => Team::Mockup,
        'pack_ship' => Team::PackShip,
        // DTG departments (PD only)
        'pick_dtg'  => Team::PickDtg,
        'dtg_print' => Team::DtgPrint,
    ];

    /**
     * @param  string     $date        Target date (Y-m-d)
     * @param  Collection $departments Departments with loaded productionLine relation
     * @return array<int, int>         Map of department_id → ton_dau (day start inventory)
     */
    public function run(string $date, Collection $departments): array
    {
        $inventoryMap = [];
        $action = app(GetDailyInventoryAction::class);

        foreach ($departments as $dept) {
            $team = self::DEPT_TEAM_MAP[$dept->code] ?? null;

            if (!$team) {
                // Unknown department code — default to 0
                $inventoryMap[$dept->id] = 0;
                continue;
            }

            try {
                $result = $action->run($date, $team);
                $inventoryMap[$dept->id] = $result['ton_dau'] ?? 0;
            } catch (\Throwable $e) {
                Log::warning('[CreateDailyShift] Fplatform query failed for dept', [
                    'department_id'   => $dept->id,
                    'department_code' => $dept->code,
                    'team'            => $team->value,
                    'error'           => $e->getMessage(),
                ]);
                $inventoryMap[$dept->id] = 0;
            }
        }

        return $inventoryMap;
    }
}
