<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * PD DTG printers — align with fplatform `printed_by` and add 3 printers.
 *
 *   sort | code    | name     | note
 *   -----|---------|----------|------------------------------------------
 *   1    | apollo  | Apollo 1 | rename only
 *   2    | atlas_1 | Atlas 2  | was atlas_01 (never matched printed_by ATLAS_1 → 0% KPI)
 *   3    | atlas_2 | Atlas 3  | was atlas_02
 *   4    | atlas4  | Matrix 4 | new (fplatform ATLAS4, printing since 2026-10-30)
 *   5    | atlas5  | Matrix 5 | new (code assumed, machine has not printed yet)
 *   6    | apollo6 | Apollo 6 | new (fplatform Apollo6, printing since 2026-10-30)
 *
 * Machine codes must equal strtolower(printed_by): SyncDepartmentHourlyJob /
 * HourlyRecord / DepartmentSummary look up per-machine KPI by that key.
 * Machine ids are preserved, so shift_detail_machines / hourly_record_machines history stays valid.
 * New machines are attached to dtg_print shift_details of active shifts dated today or later
 * (past shifts keep their history untouched) and those shift_details get kpi_per_hour /
 * machine_count recomputed from their pivots.
 *
 * FLS factory is not affected.
 */
return new class extends Migration
{
    /** @var array<int, array{code: string, old_code: ?string, name: string, kpi: int, sort: int}> */
    private const MACHINES = [
        ['code' => 'apollo',  'old_code' => null,       'name' => 'Apollo 1', 'kpi' => 280, 'sort' => 1],
        ['code' => 'atlas_1', 'old_code' => 'atlas_01', 'name' => 'Atlas 2',  'kpi' => 90,  'sort' => 2],
        ['code' => 'atlas_2', 'old_code' => 'atlas_02', 'name' => 'Atlas 3',  'kpi' => 98,  'sort' => 3],
        ['code' => 'atlas4',  'old_code' => null,       'name' => 'Matrix 4', 'kpi' => 90,  'sort' => 4],
        ['code' => 'atlas5',  'old_code' => null,       'name' => 'Matrix 5', 'kpi' => 90,  'sort' => 5],
        ['code' => 'apollo6', 'old_code' => null,       'name' => 'Apollo 6', 'kpi' => 280, 'sort' => 6],
    ];

    /** Original state of the 3 pre-existing machines, for down(). */
    private const ORIGINAL = [
        'apollo'  => ['code' => 'apollo',   'name' => 'Apollo'],
        'atlas_1' => ['code' => 'atlas_01', 'name' => 'Atlas-01'],
        'atlas_2' => ['code' => 'atlas_02', 'name' => 'Atlas-02'],
    ];

    private const NEW_CODES = ['atlas4', 'atlas5', 'apollo6'];

    public function up(): void
    {
        if (config('factory.current') !== 'PD') {
            return;
        }

        $deptId = $this->dtgPrintDepartmentId();
        if (!$deptId) {
            return;
        }

        $now = now();

        foreach (self::MACHINES as $spec) {
            $existing = DB::table('machines')
                ->where('department_id', $deptId)
                ->whereIn('code', array_filter([$spec['code'], $spec['old_code']]))
                ->orderByRaw("code = ? DESC", [$spec['code']])
                ->first();

            if ($existing) {
                // Existing machine: fix code/name/sort, keep its own kpi_per_hour (admin-managed)
                DB::table('machines')->where('id', $existing->id)->update([
                    'code'       => $spec['code'],
                    'name'       => $spec['name'],
                    'sort_order' => $spec['sort'],
                    'updated_at' => $now,
                ]);
                continue;
            }

            DB::table('machines')->insert([
                'department_id' => $deptId,
                'code'          => $spec['code'],
                'name'          => $spec['name'],
                'status'        => 'online',
                'description'   => "Máy in DTG {$spec['name']}",
                'unit'          => 'print',
                'kpi_per_hour'  => $spec['kpi'],
                'sort_order'    => $spec['sort'],
                'is_active'     => true,
                'created_at'    => $now,
                'updated_at'    => $now,
            ]);
        }

        $this->attachActiveMachinesToActiveShifts($deptId, $now);
    }

    public function down(): void
    {
        if (config('factory.current') !== 'PD') {
            return;
        }

        $deptId = $this->dtgPrintDepartmentId();
        if (!$deptId) {
            return;
        }

        $now = now();

        $newIds = DB::table('machines')
            ->where('department_id', $deptId)
            ->whereIn('code', self::NEW_CODES)
            ->pluck('id');

        if ($newIds->isNotEmpty()) {
            DB::table('hourly_record_machines')->whereIn('machine_id', $newIds)->delete();
            DB::table('shift_detail_machines')->whereIn('machine_id', $newIds)->delete();
            DB::table('machines')->whereIn('id', $newIds)->delete();
        }

        foreach (self::ORIGINAL as $currentCode => $orig) {
            DB::table('machines')
                ->where('department_id', $deptId)
                ->where('code', $currentCode)
                ->update(['code' => $orig['code'], 'name' => $orig['name'], 'updated_at' => $now]);
        }

        $this->recomputeShiftDetails($deptId, $now);
    }

    private function dtgPrintDepartmentId(): ?int
    {
        $row = DB::table('departments')
            ->join('production_lines', 'departments.production_line_id', '=', 'production_lines.id')
            ->where('production_lines.code', 'dtg')
            ->where('departments.code', 'dtg_print')
            ->select('departments.id')
            ->first();

        return $row?->id;
    }

    /**
     * For every dtg_print shift_detail of an active shift dated today or later: attach missing active machines
     * (kpi snapshot = machine kpi_per_hour), then recompute kpi_per_hour / machine_count.
     */
    private function attachActiveMachinesToActiveShifts(int $deptId, \DateTimeInterface $now): void
    {
        $activeMachines = DB::table('machines')
            ->where('department_id', $deptId)
            ->where('is_active', true)
            ->get(['id', 'kpi_per_hour']);

        $detailIds = $this->activeShiftDetailIds($deptId);

        foreach ($detailIds as $detailId) {
            $attached = DB::table('shift_detail_machines')
                ->where('shift_detail_id', $detailId)
                ->pluck('machine_id')
                ->all();

            $rows = [];
            foreach ($activeMachines as $m) {
                if (in_array($m->id, $attached, true)) {
                    continue;
                }
                $rows[] = [
                    'shift_detail_id' => $detailId,
                    'machine_id'      => $m->id,
                    'kpi_per_hour'    => $m->kpi_per_hour,
                    'created_at'      => $now,
                    'updated_at'      => $now,
                ];
            }

            if ($rows) {
                DB::table('shift_detail_machines')->insert($rows);
            }
        }

        $this->recomputeShiftDetails($deptId, $now);
    }

    private function recomputeShiftDetails(int $deptId, \DateTimeInterface $now): void
    {
        foreach ($this->activeShiftDetailIds($deptId) as $detailId) {
            $pivots = DB::table('shift_detail_machines')->where('shift_detail_id', $detailId);

            DB::table('shift_details')->where('id', $detailId)->update([
                'kpi_per_hour'  => (int) $pivots->sum('kpi_per_hour'),
                'machine_count' => (int) $pivots->count(),
                'updated_at'    => $now,
            ]);
        }
    }

    /** @return int[] */
    private function activeShiftDetailIds(int $deptId): array
    {
        return DB::table('shift_details')
            ->join('shifts', 'shift_details.shift_id', '=', 'shifts.id')
            ->where('shifts.is_active', true)
            ->whereDate('shifts.date', '>=', Carbon::today()->toDateString())
            ->where('shift_details.department_id', $deptId)
            ->pluck('shift_details.id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }
};
