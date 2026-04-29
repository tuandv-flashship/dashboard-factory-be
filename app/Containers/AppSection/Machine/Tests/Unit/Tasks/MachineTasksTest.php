<?php

namespace App\Containers\AppSection\Machine\Tests\Unit\Tasks;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Machine\Models\Machine;
use App\Containers\AppSection\Machine\Tasks\GetAllMachinesTask;
use App\Containers\AppSection\Machine\Tasks\GetMachinesByLineTask;
use App\Containers\AppSection\Machine\Tasks\UpdateMachineStatusTask;
use App\Containers\AppSection\Machine\Tests\UnitTestCase;
use App\Containers\AppSection\Production\Models\ProductionLine;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GetAllMachinesTask::class)]
#[CoversClass(GetMachinesByLineTask::class)]
#[CoversClass(UpdateMachineStatusTask::class)]
final class MachineTasksTest extends UnitTestCase
{
    private Department $dept;

    protected function setUp(): void
    {
        parent::setUp();

        $line = ProductionLine::factory()->create(['code' => 'dtf_test']);
        $this->dept = Department::create([
            'production_line_id' => $line->id,
            'code' => 'test_dept',
            'label' => 'Test Dept',
            'label_en' => 'Test Dept',
            'icon' => 'Printer',
            'unit' => 'print',
            'productivity_type' => 'per_machine_dtg',
        ]);
    }

    public function testGetAllMachinesReturnsOnlyActive(): void
    {
        Machine::create(['department_id' => $this->dept->id, 'code' => 'active-1', 'name' => 'M1', 'status' => 'online', 'sort_order' => 1, 'is_active' => true]);
        Machine::create(['department_id' => $this->dept->id, 'code' => 'inactive-1', 'name' => 'M2', 'status' => 'online', 'sort_order' => 2, 'is_active' => false]);

        $result = app(GetAllMachinesTask::class)->run();

        $this->assertCount(1, $result);
        $this->assertSame('active-1', $result->first()->code);
    }

    public function testGetAllMachinesOrderedBySortOrder(): void
    {
        Machine::create(['department_id' => $this->dept->id, 'code' => 'z-last', 'name' => 'ML', 'status' => 'online', 'sort_order' => 5]);
        Machine::create(['department_id' => $this->dept->id, 'code' => 'a-first', 'name' => 'MF', 'status' => 'online', 'sort_order' => 1]);

        $result = app(GetAllMachinesTask::class)->run();

        $this->assertSame('a-first', $result->first()->code);
        $this->assertSame('z-last', $result->last()->code);
    }

    public function testGetMachinesByLineFilters(): void
    {
        $dtgLine = ProductionLine::factory()->create(['code' => 'dtg_test']);
        $dtgDept = Department::create([
            'production_line_id' => $dtgLine->id,
            'code' => 'dtg_dept',
            'label' => 'DTG Dept',
            'label_en' => 'DTG Dept',
            'icon' => 'Printer',
        ]);

        Machine::create(['department_id' => $this->dept->id, 'code' => 'dtf-m1', 'name' => 'M1', 'status' => 'online', 'sort_order' => 1]);
        Machine::create(['department_id' => $dtgDept->id, 'code' => 'dtg-m1', 'name' => 'M2', 'status' => 'online', 'sort_order' => 2]);

        $result = app(GetMachinesByLineTask::class)->run('dtf_test');

        $this->assertCount(1, $result);
        $this->assertSame('dtf-m1', $result->first()->code);
    }

    public function testUpdateMachineStatus(): void
    {
        $machine = Machine::create(['department_id' => $this->dept->id, 'code' => 'test-m', 'name' => 'Test', 'status' => 'online', 'sort_order' => 1]);

        $result = app(UpdateMachineStatusTask::class)->run($machine->id, 'maintenance');

        $this->assertSame('maintenance', $result->status->value);
        $this->assertSame('maintenance', $machine->fresh()->status->value);
    }
}
