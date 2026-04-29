<?php

namespace App\Containers\AppSection\Shift\Tests\Unit;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Machine\Models\Machine;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Models\ShiftDetailMachine;
use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Containers\AppSection\Shift\Models\ShiftTemplateDetail;
use App\Containers\AppSection\Shift\Tasks\CreateShiftFromTemplateTask;
use App\Containers\AppSection\Shift\Tasks\GenerateHourlyRecordsTask;
use App\Containers\AppSection\Shift\Tasks\SyncShiftDetailsTask;
use App\Containers\AppSection\Shift\Tasks\UpdateHourlyStaffTask;
use App\Containers\AppSection\Shift\Tests\UnitTestCase;

/**
 * Unit tests for machine selection in per_machine departments (DTG Print).
 *
 * Test coverage:
 *   - CreateShiftFromTemplateTask: machine pivot creation + kpi snapshot
 *   - GenerateHourlyRecordsTask: per_machine target = Σ(machine KPIs)
 *   - SyncShiftDetailsTask: machine sync on update
 *   - UpdateHourlyStaffTask: per_machine target stays fixed when staff changes
 */
final class MachineShiftTest extends UnitTestCase
{
    private ProductionLine $line;
    private Department $perPersonDept;
    private Department $perMachineDept;
    private Machine $machine1;
    private Machine $machine2;
    private Machine $machine3;
    private ShiftTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->line = ProductionLine::create([
            'code' => 'dtg', 'label' => 'DTG', 'color' => '#8b5cf6',
            'sort_order' => 1, 'is_active' => true,
        ]);

        $this->perPersonDept = Department::create([
            'production_line_id' => $this->line->id,
            'code' => 'dtg_pick', 'label' => 'DTG Pick', 'label_en' => 'DTG Pick',
            'icon' => 'Package', 'unit' => 'file', 'sort_order' => 1, 'is_active' => true,
            'kpi_per_hour' => 50,
            'productivity_type' => ProductivityType::PerPerson->value,
        ]);

        $this->perMachineDept = Department::create([
            'production_line_id' => $this->line->id,
            'code' => 'dtg_print', 'label' => 'DTG Print', 'label_en' => 'DTG Print',
            'icon' => 'Printer', 'unit' => 'print', 'sort_order' => 2, 'is_active' => true,
            'kpi_per_hour' => 0, // per_machine: KPI comes from machines, not dept
            'productivity_type' => ProductivityType::PerMachineDtg->value,
        ]);

        $this->machine1 = Machine::create([
            'department_id' => $this->perMachineDept->id,
            'code' => 'apollo', 'name' => 'Apollo', 'status' => 'online',
            'unit' => 'print', 'kpi_per_hour' => 250, 'sort_order' => 1, 'is_active' => true,
        ]);
        $this->machine2 = Machine::create([
            'department_id' => $this->perMachineDept->id,
            'code' => 'atlas_01', 'name' => 'Atlas-01', 'status' => 'online',
            'unit' => 'print', 'kpi_per_hour' => 75, 'sort_order' => 2, 'is_active' => true,
        ]);
        $this->machine3 = Machine::create([
            'department_id' => $this->perMachineDept->id,
            'code' => 'atlas_02', 'name' => 'Atlas-02', 'status' => 'offline',
            'unit' => 'print', 'kpi_per_hour' => 75, 'sort_order' => 3, 'is_active' => true,
        ]);

        $this->template = ShiftTemplate::create([
            'name' => 'Test Template', 'color' => '#0000FF',
            'sort_order' => 1, 'status' => 'active',
            'applies_to_shift_1' => true, 'applies_to_shift_2' => false,
        ]);

        // Template detail: per_person
        ShiftTemplateDetail::create([
            'shift_template_id' => $this->template->id,
            'department_id' => $this->perPersonDept->id,
            'shift_number' => 1,
            'headcount' => 5,
            'start_time' => '06:00:00', 'work_hours' => 8, 'prep_minutes' => 0,
            'break1_start' => null, 'break1_minutes' => 0,
            'meal_break_start' => '11:00', 'meal_break_minutes' => 30,
            'break2_start' => null, 'break2_minutes' => 0,
            'break3_start' => null, 'break3_minutes' => 0,
        ]);

        // Template detail: per_machine (DTG Print)
        ShiftTemplateDetail::create([
            'shift_template_id' => $this->template->id,
            'department_id' => $this->perMachineDept->id,
            'shift_number' => 1,
            'headcount' => 3,
            'start_time' => '06:00:00', 'work_hours' => 8, 'prep_minutes' => 0,
            'break1_start' => null, 'break1_minutes' => 0,
            'meal_break_start' => '11:00', 'meal_break_minutes' => 30,
            'break2_start' => null, 'break2_minutes' => 0,
            'break3_start' => null, 'break3_minutes' => 0,
        ]);
    }

    // ── Helper ──────────────────────────────────────────

    private function createShift(): Shift
    {
        return Shift::create([
            'date' => '2026-04-10',
            'shift_number' => 1,
            'start_time' => '06:00',
            'end_time' => '14:30',
            'is_active' => true,
            'shift_template_id' => $this->template->id,
        ]);
    }

    // ── CreateShiftFromTemplateTask ─────────────────────

    public function testCreateShiftAttachesMachinesAndSnapshotsKpi(): void
    {
        $shift = $this->createShift();
        $overrides = [
            [
                'department_id' => $this->perMachineDept->id,
                'shift_number' => 1,
                'machine_ids' => [$this->machine1->id, $this->machine2->id],
            ],
        ];

        app(CreateShiftFromTemplateTask::class)->run($shift, $this->template->id, $overrides);

        // Verify shift_details created
        $this->assertDatabaseCount('shift_details', 2);

        // Verify per_machine detail has kpi = 250 + 75 = 325
        $machineDetail = ShiftDetail::where('shift_id', $shift->id)
            ->where('department_id', $this->perMachineDept->id)
            ->first();
        $this->assertSame(325, $machineDetail->kpi_per_hour);

        // Verify per_person detail has kpi from department
        $personDetail = ShiftDetail::where('shift_id', $shift->id)
            ->where('department_id', $this->perPersonDept->id)
            ->first();
        $this->assertSame(50, $personDetail->kpi_per_hour);

        // Verify pivot records created with snapshot KPIs
        $pivots = ShiftDetailMachine::where('shift_detail_id', $machineDetail->id)->get();
        $this->assertCount(2, $pivots);
        $this->assertSame(250, $pivots->where('machine_id', $this->machine1->id)->first()->kpi_per_hour);
        $this->assertSame(75, $pivots->where('machine_id', $this->machine2->id)->first()->kpi_per_hour);
    }

    public function testCreateShiftWithNoMachinesResultsInZeroKpi(): void
    {
        $shift = $this->createShift();

        // No overrides → no machine_ids → kpi stays 0
        app(CreateShiftFromTemplateTask::class)->run($shift, $this->template->id, []);

        $machineDetail = ShiftDetail::where('shift_id', $shift->id)
            ->where('department_id', $this->perMachineDept->id)
            ->first();

        $this->assertSame(0, $machineDetail->kpi_per_hour);
        $this->assertDatabaseCount('shift_detail_machines', 0);
    }

    public function testCreateShiftIgnoresMachinesFromWrongDepartment(): void
    {
        // Create a machine belonging to perPersonDept (wrong dept)
        $wrongMachine = Machine::create([
            'department_id' => $this->perPersonDept->id,
            'code' => 'wrong', 'name' => 'Wrong', 'status' => 'online',
            'unit' => 'print', 'kpi_per_hour' => 999, 'sort_order' => 99, 'is_active' => true,
        ]);

        $shift = $this->createShift();
        $overrides = [
            [
                'department_id' => $this->perMachineDept->id,
                'shift_number' => 1,
                'machine_ids' => [$this->machine1->id, $wrongMachine->id],
            ],
        ];

        app(CreateShiftFromTemplateTask::class)->run($shift, $this->template->id, $overrides);

        $machineDetail = ShiftDetail::where('shift_id', $shift->id)
            ->where('department_id', $this->perMachineDept->id)
            ->first();

        // Only machine1 should be attached (250), wrongMachine silently ignored
        $this->assertSame(250, $machineDetail->kpi_per_hour);
        $this->assertDatabaseCount('shift_detail_machines', 1);
    }

    // ── GenerateHourlyRecordsTask ──────────────────────

    public function testGenerateHourlyRecordsPerMachineTargetNotMultipliedByHeadcount(): void
    {
        $shift = $this->createShift();
        $overrides = [
            [
                'department_id' => $this->perMachineDept->id,
                'shift_number' => 1,
                'machine_ids' => [$this->machine1->id, $this->machine2->id], // 250+75=325
            ],
        ];

        app(CreateShiftFromTemplateTask::class)->run($shift, $this->template->id, $overrides);
        app(GenerateHourlyRecordsTask::class)->run($shift);

        // Per-machine: all hourly records should have target = 325 (NOT × headcount)
        $machineRecords = HourlyRecord::where('shift_id', $shift->id)
            ->where('department_id', $this->perMachineDept->id)
            ->get();

        $this->assertTrue($machineRecords->isNotEmpty());
        // Business rule: per-machine target = kpi_per_hour × kpi_percent/100 (NOT × headcount)
        // Full-hour slots → 325, partial last slot → round(325 × fraction)
        $machineRecords->each(fn ($r) => $this->assertSame(
            (int) round(325 * $r->kpi_percent / 100),
            $r->target,
            "Slot {$r->hour_slot}: expected target proportional to kpi_percent, not multiplied by headcount"
        ));

        // Per-person: target = 50 × 5 = 250
        $personRecords = HourlyRecord::where('shift_id', $shift->id)
            ->where('department_id', $this->perPersonDept->id)
            ->get();

        // Per-person: target = fullHourTarget × kpi_percent/100 (250 for full slots, less for partial)
        $personRecords->each(fn ($r) => $this->assertSame(
            (int) round(250 * $r->kpi_percent / 100),
            $r->target
        ));
    }

    // ── SyncShiftDetailsTask ──────────────────────────

    public function testSyncDetailsUpdatesMachines(): void
    {
        $shift = $this->createShift();
        $overrides = [
            [
                'department_id' => $this->perMachineDept->id,
                'shift_number' => 1,
                'machine_ids' => [$this->machine1->id, $this->machine2->id], // 250+75=325
            ],
        ];
        app(CreateShiftFromTemplateTask::class)->run($shift, $this->template->id, $overrides);

        // Now sync with different machines: machine1 + machine3 (250+75=325)
        $detailsData = [
            [
                'department_id' => $this->perMachineDept->id,
                'shift_number' => 1,
                'headcount' => 3,
                'kpi_per_hour' => 0, // will be recomputed
                'day_start_inventory' => 0,
                'start_time' => '06:00:00', 'work_hours' => 8, 'prep_minutes' => 0,
                'break1_start' => null, 'break1_minutes' => 0,
                'meal_break_start' => '11:00', 'meal_break_minutes' => 30,
                'break2_start' => null, 'break2_minutes' => 0,
                'break3_start' => null, 'break3_minutes' => 0,
                'machine_ids' => [$this->machine1->id, $this->machine3->id],
            ],
        ];

        app(SyncShiftDetailsTask::class)->run($shift, $detailsData);

        // Verify: machine2 replaced by machine3
        $machineDetail = ShiftDetail::where('shift_id', $shift->id)
            ->where('department_id', $this->perMachineDept->id)
            ->first();

        $pivots = ShiftDetailMachine::where('shift_detail_id', $machineDetail->id)->get();
        $this->assertCount(2, $pivots);
        $this->assertTrue($pivots->pluck('machine_id')->contains($this->machine1->id));
        $this->assertTrue($pivots->pluck('machine_id')->contains($this->machine3->id));
        $this->assertFalse($pivots->pluck('machine_id')->contains($this->machine2->id));

        // kpi_per_hour = 250 + 75 = 325
        $this->assertSame(325, $machineDetail->fresh()->kpi_per_hour);
    }

    public function testSyncDetailsWithEmptyMachineIdsRemovesAll(): void
    {
        $shift = $this->createShift();
        $overrides = [
            [
                'department_id' => $this->perMachineDept->id,
                'shift_number' => 1,
                'machine_ids' => [$this->machine1->id],
            ],
        ];
        app(CreateShiftFromTemplateTask::class)->run($shift, $this->template->id, $overrides);

        // Sync with empty machine_ids → remove all
        $detailsData = [
            [
                'department_id' => $this->perMachineDept->id,
                'shift_number' => 1,
                'headcount' => 3, 'kpi_per_hour' => 0, 'day_start_inventory' => 0,
                'start_time' => '06:00:00', 'work_hours' => 8, 'prep_minutes' => 0,
                'break1_start' => null, 'break1_minutes' => 0,
                'meal_break_start' => null, 'meal_break_minutes' => 0,
                'break2_start' => null, 'break2_minutes' => 0,
                'break3_start' => null, 'break3_minutes' => 0,
                'machine_ids' => [],
            ],
        ];

        app(SyncShiftDetailsTask::class)->run($shift, $detailsData);

        $machineDetail = ShiftDetail::where('shift_id', $shift->id)
            ->where('department_id', $this->perMachineDept->id)
            ->first();

        $this->assertSame(0, $machineDetail->kpi_per_hour);
        $this->assertDatabaseCount('shift_detail_machines', 0);
    }

    // ── UpdateHourlyStaffTask ─────────────────────────

    public function testUpdateStaffPerMachineKeepsTargetFixed(): void
    {
        $shift = $this->createShift();
        $overrides = [
            [
                'department_id' => $this->perMachineDept->id,
                'shift_number' => 1,
                'machine_ids' => [$this->machine1->id], // kpi = 250
            ],
        ];
        app(CreateShiftFromTemplateTask::class)->run($shift, $this->template->id, $overrides);
        app(GenerateHourlyRecordsTask::class)->run($shift);

        $hourlyRecord = HourlyRecord::where('shift_id', $shift->id)
            ->where('department_id', $this->perMachineDept->id)
            ->first();

        $this->assertSame(250, $hourlyRecord->target);
        $this->assertSame(3, $hourlyRecord->staff); // from template headcount

        // Now change staff to 5 → target should still be 250
        app(UpdateHourlyStaffTask::class)->run([
            ['id' => $hourlyRecord->id, 'staff' => 5],
        ]);

        $hourlyRecord->refresh();
        $this->assertSame(5, $hourlyRecord->staff);
        $this->assertSame(250, $hourlyRecord->target); // UNCHANGED!
    }

    public function testUpdateStaffPerPersonRecalculatesTarget(): void
    {
        $shift = $this->createShift();
        app(CreateShiftFromTemplateTask::class)->run($shift, $this->template->id, []);
        app(GenerateHourlyRecordsTask::class)->run($shift);

        $hourlyRecord = HourlyRecord::where('shift_id', $shift->id)
            ->where('department_id', $this->perPersonDept->id)
            ->first();

        // Original: target = 50 × 5 = 250
        $this->assertSame(250, $hourlyRecord->target);

        // Change staff to 10 → target = 50 × 10 = 500
        app(UpdateHourlyStaffTask::class)->run([
            ['id' => $hourlyRecord->id, 'staff' => 10],
        ]);

        $hourlyRecord->refresh();
        $this->assertSame(10, $hourlyRecord->staff);
        $this->assertSame(500, $hourlyRecord->target);
    }
}
