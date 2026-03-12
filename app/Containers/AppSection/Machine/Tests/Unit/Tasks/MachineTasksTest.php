<?php

namespace App\Containers\AppSection\Machine\Tests\Unit\Tasks;

use App\Containers\AppSection\Machine\Models\Machine;
use App\Containers\AppSection\Machine\Tasks\GetAllMachinesTask;
use App\Containers\AppSection\Machine\Tasks\GetMachinesByLineTask;
use App\Containers\AppSection\Machine\Tasks\UpdateMachineStatusTask;
use App\Containers\AppSection\Machine\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GetAllMachinesTask::class)]
#[CoversClass(GetMachinesByLineTask::class)]
#[CoversClass(UpdateMachineStatusTask::class)]
final class MachineTasksTest extends UnitTestCase
{
    public function testGetAllMachinesReturnsOnlyActive(): void
    {
        Machine::create(['code' => 'active-1', 'name' => 'M1', 'status' => 'online', 'department' => 'print', 'line' => 'dtf1', 'sort_order' => 1, 'is_active' => true]);
        Machine::create(['code' => 'inactive-1', 'name' => 'M2', 'status' => 'online', 'department' => 'print', 'line' => 'dtf1', 'sort_order' => 2, 'is_active' => false]);

        $result = app(GetAllMachinesTask::class)->run();

        $this->assertCount(1, $result);
        $this->assertSame('active-1', $result->first()->code);
    }

    public function testGetAllMachinesOrderedBySortOrder(): void
    {
        Machine::create(['code' => 'z-last', 'name' => 'ML', 'status' => 'online', 'department' => 'print', 'line' => 'dtf1', 'sort_order' => 5]);
        Machine::create(['code' => 'a-first', 'name' => 'MF', 'status' => 'online', 'department' => 'print', 'line' => 'dtf1', 'sort_order' => 1]);

        $result = app(GetAllMachinesTask::class)->run();

        $this->assertSame('a-first', $result->first()->code);
        $this->assertSame('z-last', $result->last()->code);
    }

    public function testGetMachinesByLineFilters(): void
    {
        Machine::create(['code' => 'dtf1-m1', 'name' => 'M1', 'status' => 'online', 'department' => 'print', 'line' => 'dtf1', 'sort_order' => 1]);
        Machine::create(['code' => 'dtf2-m1', 'name' => 'M2', 'status' => 'online', 'department' => 'print', 'line' => 'dtf2', 'sort_order' => 2]);

        $result = app(GetMachinesByLineTask::class)->run('dtf1');

        $this->assertCount(1, $result);
        $this->assertSame('dtf1-m1', $result->first()->code);
    }

    public function testUpdateMachineStatus(): void
    {
        $machine = Machine::create(['code' => 'test-m', 'name' => 'Test', 'status' => 'online', 'department' => 'print', 'line' => 'dtf1', 'sort_order' => 1]);

        $result = app(UpdateMachineStatusTask::class)->run($machine->id, 'maintenance');

        $this->assertSame('maintenance', $result->status);
        $this->assertSame('maintenance', $machine->fresh()->status);
    }
}
