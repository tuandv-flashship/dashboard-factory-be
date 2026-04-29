<?php

namespace App\Containers\AppSection\Shift\Tests\Unit;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Shift\Enums\ShiftTemplateStatus;
use App\Containers\AppSection\Shift\Tasks\FetchDailyInventoryForShiftTask;
use App\Containers\AppSection\Machine\Models\Machine;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Shift\Actions\CreateDailyShiftAction;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Models\ShiftDetailMachine;
use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Containers\AppSection\Shift\Models\ShiftTemplateDetail;
use App\Containers\AppSection\Shift\Tests\UnitTestCase;
use Mockery;

/**
 * Unit tests for CreateDailyShiftAction.
 *
 * Test coverage:
 *   - Happy path: creates shift + details + hourly_records
 *   - Auto-selects all active machines (skips inactive)
 *   - Idempotent: skips when shift exists
 *   - Skips when no active template
 *   - Fplatform inventory integration
 *   - Custom date parameter
 */
final class CreateDailyShiftJobTest extends UnitTestCase
{
    private ProductionLine $dtfLine;
    private ProductionLine $dtgLine;
    private Department $printDept;
    private Department $pickDept;
    private Department $perMachineDept;
    private Machine $activeMachine1;
    private Machine $activeMachine2;
    private Machine $inactiveMachine;
    private ShiftTemplate $template;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dtfLine = ProductionLine::firstOrCreate(
            ['code' => 'dtf'],
            ['label' => 'DTF', 'color' => '#3b82f6', 'sort_order' => 1, 'is_active' => true],
        );

        $this->dtgLine = ProductionLine::firstOrCreate(
            ['code' => 'dtg'],
            ['label' => 'DTG', 'color' => '#8b5cf6', 'sort_order' => 2, 'is_active' => true],
        );

        $this->printDept = Department::firstOrCreate(
            ['code' => 'print', 'production_line_id' => $this->dtfLine->id],
            [
                'label' => 'In ấn', 'label_en' => 'Print',
                'icon' => 'Printer', 'unit' => 'file', 'sort_order' => 1, 'is_active' => true,
                'kpi_per_hour' => 130,
                'productivity_type' => ProductivityType::PerPerson->value,
            ],
        );

        $this->pickDept = Department::firstOrCreate(
            ['code' => 'pick', 'production_line_id' => $this->dtfLine->id],
            [
                'label' => 'Pick', 'label_en' => 'Pick',
                'icon' => 'ShoppingCart', 'unit' => 'shirt', 'sort_order' => 2, 'is_active' => true,
                'kpi_per_hour' => 180,
                'productivity_type' => ProductivityType::PerPerson->value,
            ],
        );

        $this->perMachineDept = Department::firstOrCreate(
            ['code' => 'dtg_print', 'production_line_id' => $this->dtgLine->id],
            [
                'label' => 'DTG Print', 'label_en' => 'DTG Print',
                'icon' => 'Printer', 'unit' => 'print', 'sort_order' => 1, 'is_active' => true,
                'kpi_per_hour' => 0,
                'productivity_type' => ProductivityType::PerMachineDtg->value,
            ],
        );

        $this->activeMachine1 = Machine::firstOrCreate(
            ['code' => 'dj_apollo', 'department_id' => $this->perMachineDept->id],
            [
                'name' => 'Apollo', 'status' => 'online',
                'unit' => 'print', 'kpi_per_hour' => 250, 'sort_order' => 1, 'is_active' => true,
            ],
        );
        $this->activeMachine2 = Machine::firstOrCreate(
            ['code' => 'dj_atlas_01', 'department_id' => $this->perMachineDept->id],
            [
                'name' => 'Atlas-01', 'status' => 'online',
                'unit' => 'print', 'kpi_per_hour' => 75, 'sort_order' => 2, 'is_active' => true,
            ],
        );
        $this->inactiveMachine = Machine::firstOrCreate(
            ['code' => 'dj_atlas_02', 'department_id' => $this->perMachineDept->id],
            [
                'name' => 'Atlas-02', 'status' => 'offline',
                'unit' => 'print', 'kpi_per_hour' => 75, 'sort_order' => 3, 'is_active' => false,
            ],
        );

        // Deactivate any pre-existing templates (from seeders)
        ShiftTemplate::query()->update(['status' => ShiftTemplateStatus::INACTIVE]);

        $this->template = ShiftTemplate::create([
            'name' => 'Ca 1 Test',
            'color' => '#0000FF', 'sort_order' => 1, 'status' => ShiftTemplateStatus::ACTIVE,
            'applies_to_shift_1' => true, 'applies_to_shift_2' => false,
        ]);

        // Template details for shift_number=1
        ShiftTemplateDetail::create([
            'shift_template_id' => $this->template->id,
            'department_id' => $this->printDept->id,
            'shift_number' => 1, 'headcount' => 8,
            'start_time' => '06:30:00', 'work_hours' => 8, 'prep_minutes' => 23,
            'break1_start' => '09:00', 'break1_minutes' => 15,
            'meal_break_start' => '11:30', 'meal_break_minutes' => 30,
            'break2_start' => '14:00', 'break2_minutes' => 15,
            'break3_start' => '16:30', 'break3_minutes' => 15,
        ]);

        ShiftTemplateDetail::create([
            'shift_template_id' => $this->template->id,
            'department_id' => $this->pickDept->id,
            'shift_number' => 1, 'headcount' => 3,
            'start_time' => '06:00:00', 'work_hours' => 8, 'prep_minutes' => 0,
            'break1_start' => '08:30', 'break1_minutes' => 15,
            'meal_break_start' => '11:00', 'meal_break_minutes' => 30,
            'break2_start' => null, 'break2_minutes' => 0,
            'break3_start' => null, 'break3_minutes' => 0,
        ]);

        ShiftTemplateDetail::create([
            'shift_template_id' => $this->template->id,
            'department_id' => $this->perMachineDept->id,
            'shift_number' => 1, 'headcount' => 3,
            'start_time' => '06:30:00', 'work_hours' => 8, 'prep_minutes' => 20,
            'break1_start' => '09:00', 'break1_minutes' => 15,
            'meal_break_start' => '11:00', 'meal_break_minutes' => 30,
            'break2_start' => null, 'break2_minutes' => 0,
            'break3_start' => null, 'break3_minutes' => 0,
        ]);

        // Mock Fplatform — return null by default (no inventory data)
        $this->mockFplatformInventory();
    }

    /**
     * Mock FetchDailyInventoryForShiftTask to avoid hitting the fplatform DB.
     *
     * @param  array<int, int>|null $inventoryMap  dept_id → tong_viec, or null for empty
     */
    private function mockFplatformInventory(?array $inventoryMap = null): void
    {
        $mock = Mockery::mock(FetchDailyInventoryForShiftTask::class);
        $mock->shouldReceive('run')
            ->andReturn($inventoryMap ?? []);

        $this->app->instance(FetchDailyInventoryForShiftTask::class, $mock);
    }

    // ── Happy Path ──────────────────────────────────────

    public function testCreatesShift1FromDefaultTemplate(): void
    {
        $date = '2026-05-01';
        $result = app(CreateDailyShiftAction::class)->run($date);

        $this->assertSame('created', $result['status']);
        $this->assertArrayHasKey('shift', $result);

        // Verify shift header
        $shift = $result['shift'];
        $this->assertSame($date, $shift->date->toDateString());
        $this->assertSame(1, $shift->shift_number);
        $this->assertTrue($shift->is_active);
        $this->assertSame($this->template->id, $shift->shift_template_id);

        // Verify details created (3 departments)
        $this->assertSame(3, ShiftDetail::where('shift_id', $shift->id)->count());

        // Verify hourly records generated
        $this->assertTrue(HourlyRecord::where('shift_id', $shift->id)->exists());
    }

    // ── Auto Machine Selection ──────────────────────────

    public function testAutoSelectsAllActiveMachines(): void
    {
        $result = app(CreateDailyShiftAction::class)->run('2026-05-02');

        $this->assertSame('created', $result['status']);

        // Per-machine detail should have kpi = 250 + 75 = 325 (2 active machines)
        $machineDetail = ShiftDetail::where('shift_id', $result['shift']->id)
            ->where('department_id', $this->perMachineDept->id)
            ->first();

        $this->assertSame(325, $machineDetail->kpi_per_hour);

        // 2 pivot records (active machines only)
        $pivots = ShiftDetailMachine::where('shift_detail_id', $machineDetail->id)->get();
        $this->assertCount(2, $pivots);
        $this->assertTrue($pivots->pluck('machine_id')->contains($this->activeMachine1->id));
        $this->assertTrue($pivots->pluck('machine_id')->contains($this->activeMachine2->id));
    }

    public function testSkipsInactiveMachines(): void
    {
        $result = app(CreateDailyShiftAction::class)->run('2026-05-03');

        $machineDetail = ShiftDetail::where('shift_id', $result['shift']->id)
            ->where('department_id', $this->perMachineDept->id)
            ->first();

        $pivots = ShiftDetailMachine::where('shift_detail_id', $machineDetail->id)->get();

        // Inactive machine (atlas_02) should NOT be attached
        $this->assertFalse($pivots->pluck('machine_id')->contains($this->inactiveMachine->id));
    }
    // ── Idempotent ──────────────────────────────────────

    public function testUpdatesInventoryWhenShiftAlreadyExists(): void
    {
        $date = '2099-12-25';

        // First call — should create (with 0 inventory from null mock)
        $result1 = app(CreateDailyShiftAction::class)->run($date);
        $this->assertSame('created', $result1['status']);

        // Mock Fplatform with real inventory for second call
        $this->mockFplatformInventory([
            $this->printDept->id      => 100,
            $this->pickDept->id       => 200,
            $this->perMachineDept->id => 300,
        ]);

        // Second call — should update inventory (not create new shift)
        $result2 = app(CreateDailyShiftAction::class)->run($date);
        $this->assertSame('inventory_updated', $result2['status']);
        $this->assertArrayHasKey('shift', $result2);

        // Still only 1 shift for this date
        $this->assertSame(1, Shift::whereDate('date', $date)->where('shift_number', 1)->count());
    }

    public function testUpdatesInventoryValuesOnExistingShift(): void
    {
        $date = '2099-12-26';

        // Create shift first (with default 0 inventory from null mock)
        $result1 = app(CreateDailyShiftAction::class)->run($date);
        $this->assertSame('created', $result1['status']);

        // Verify initial inventory is 0
        $printDetail = ShiftDetail::where('shift_id', $result1['shift']->id)
            ->where('department_id', $this->printDept->id)
            ->first();
        $this->assertSame(0, $printDetail->day_start_inventory);

        // Re-mock Fplatform with actual inventory values
        $this->mockFplatformInventory([
            $this->printDept->id      => 1500,
            $this->pickDept->id       => 900,
            $this->perMachineDept->id => 600,
        ]);

        // Second call — should update inventory on existing shift
        $result2 = app(CreateDailyShiftAction::class)->run($date);
        $this->assertSame('inventory_updated', $result2['status']);

        // Verify inventory was updated
        $printDetail->refresh();
        $this->assertSame(1500, $printDetail->day_start_inventory);

        $pickDetail = ShiftDetail::where('shift_id', $result1['shift']->id)
            ->where('department_id', $this->pickDept->id)
            ->first();
        $this->assertSame(900, $pickDetail->day_start_inventory);

        $dtgDetail = ShiftDetail::where('shift_id', $result1['shift']->id)
            ->where('department_id', $this->perMachineDept->id)
            ->first();
        $this->assertSame(600, $dtgDetail->day_start_inventory);
    }

    // ── No Template ─────────────────────────────────────

    public function testSkipsWhenNoActiveTemplateFound(): void
    {
        // Deactivate the template
        $this->template->update(['status' => ShiftTemplateStatus::INACTIVE]);

        $result = app(CreateDailyShiftAction::class)->run('2026-05-05');

        $this->assertSame('no_template', $result['status']);
        $this->assertSame(0, Shift::where('date', '2026-05-05')->count());
    }

    // ── Custom Date ─────────────────────────────────────

    public function testCustomDateParameter(): void
    {
        $result = app(CreateDailyShiftAction::class)->run('2026-06-15');

        $this->assertSame('created', $result['status']);
        $this->assertSame('2026-06-15', $result['shift']->date->toDateString());
    }

    // ── Fplatform Inventory ─────────────────────────────

    public function testFetchesInventoryFromFplatform(): void
    {
        // Re-mock with specific inventory values (dept_id → tong_viec)
        $this->mockFplatformInventory([
            $this->printDept->id      => 1250,
            $this->pickDept->id       => 800,
            $this->perMachineDept->id => 500,
        ]);

        $result = app(CreateDailyShiftAction::class)->run('2026-05-06');

        $this->assertSame('created', $result['status']);

        // Print dept inventory = 1250
        $printDetail = ShiftDetail::where('shift_id', $result['shift']->id)
            ->where('department_id', $this->printDept->id)
            ->first();
        $this->assertSame(1250, $printDetail->day_start_inventory);

        // Pick dept inventory = 800
        $pickDetail = ShiftDetail::where('shift_id', $result['shift']->id)
            ->where('department_id', $this->pickDept->id)
            ->first();
        $this->assertSame(800, $pickDetail->day_start_inventory);

        // DTG Print dept inventory = 500
        $dtgDetail = ShiftDetail::where('shift_id', $result['shift']->id)
            ->where('department_id', $this->perMachineDept->id)
            ->first();
        $this->assertSame(500, $dtgDetail->day_start_inventory);
    }

    public function testFallbackToZeroWhenFplatformFails(): void
    {
        // Mock FetchDailyInventoryForShiftTask to throw exception
        $mock = Mockery::mock(FetchDailyInventoryForShiftTask::class);
        $mock->shouldReceive('run')->andThrow(new \RuntimeException('Connection refused'));
        $this->app->instance(FetchDailyInventoryForShiftTask::class, $mock);

        $result = app(CreateDailyShiftAction::class)->run('2026-05-07');

        // Should still create shift successfully
        $this->assertSame('created', $result['status']);

        // All inventories should be 0
        $details = ShiftDetail::where('shift_id', $result['shift']->id)->get();
        $details->each(fn ($d) => $this->assertSame(0, $d->day_start_inventory));
    }
}
