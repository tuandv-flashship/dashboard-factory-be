<?php

namespace App\Containers\AppSection\Machine\Tests\Unit\Models;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Machine\Enums\MachineStatus;
use App\Containers\AppSection\Machine\Models\Machine;
use App\Containers\AppSection\Machine\Tests\UnitTestCase;
use App\Containers\AppSection\Production\Models\ProductionLine;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Machine::class)]
final class MachineTest extends UnitTestCase
{
    public function testUsesCorrectTable(): void
    {
        $machine = new Machine();

        $this->assertSame('machines', $machine->getTable());
    }

    public function testHasCorrectFillableFields(): void
    {
        $machine = new Machine();
        $expected = ['department_id', 'code', 'name', 'status', 'description', 'unit', 'kpi_per_hour', 'sort_order', 'is_active'];

        $this->assertSame($expected, $machine->getFillable());
    }

    public function testHasCorrectCasts(): void
    {
        $machine = new Machine();
        $casts = $machine->getCasts();

        $this->assertSame('integer', $casts['sort_order']);
        $this->assertSame('boolean', $casts['is_active']);
        $this->assertSame('integer', $casts['kpi_per_hour']);
        $this->assertSame(MachineStatus::class, $casts['status']);
    }

    public function testBelongsToDepartment(): void
    {
        $line = ProductionLine::factory()->create(['code' => 'dtg']);
        $dept = Department::create([
            'production_line_id' => $line->id,
            'code' => 'dtg_print',
            'label' => 'DTG Print',
            'label_en' => 'DTG Print',
            'icon' => 'Printer',
            'unit' => 'print',
            'productivity_type' => 'per_machine',
        ]);

        $machine = Machine::create([
            'department_id' => $dept->id,
            'code' => 'apollo',
            'name' => 'Apollo',
            'status' => 'online',
            'unit' => 'print',
            'kpi_per_hour' => 250,
            'sort_order' => 1,
        ]);

        $this->assertSame($dept->id, $machine->department->id);
    }

    public function testScopeByStatusFiltersCorrectly(): void
    {
        $line = ProductionLine::factory()->create(['code' => 'test']);
        $dept = Department::create([
            'production_line_id' => $line->id,
            'code' => 'test_dept',
            'label' => 'Test',
            'label_en' => 'Test',
            'icon' => 'Layers',
        ]);

        Machine::create(['department_id' => $dept->id, 'code' => 'test-on', 'name' => 'T1', 'status' => 'online', 'sort_order' => 1]);
        Machine::create(['department_id' => $dept->id, 'code' => 'test-off', 'name' => 'T2', 'status' => 'offline', 'sort_order' => 2]);

        $result = Machine::query()->byStatus('offline')->get();

        $this->assertCount(1, $result);
        $this->assertSame('test-off', $result->first()->code);
    }

    public function testScopeActiveFiltersCorrectly(): void
    {
        $line = ProductionLine::factory()->create(['code' => 'test2']);
        $dept = Department::create([
            'production_line_id' => $line->id,
            'code' => 'test_dept2',
            'label' => 'Test',
            'label_en' => 'Test',
            'icon' => 'Layers',
        ]);

        Machine::create(['department_id' => $dept->id, 'code' => 'test-active', 'name' => 'T1', 'status' => 'online', 'sort_order' => 1, 'is_active' => true]);
        Machine::create(['department_id' => $dept->id, 'code' => 'test-inactive', 'name' => 'T2', 'status' => 'online', 'sort_order' => 2, 'is_active' => false]);

        $result = Machine::query()->active()->get();

        $this->assertCount(1, $result);
        $this->assertSame('test-active', $result->first()->code);
    }
}
