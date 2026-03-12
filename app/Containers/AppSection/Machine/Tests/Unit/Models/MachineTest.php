<?php

namespace App\Containers\AppSection\Machine\Tests\Unit\Models;

use App\Containers\AppSection\Machine\Models\Machine;
use App\Containers\AppSection\Machine\Tests\UnitTestCase;
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
        $expected = ['code', 'name', 'status', 'department', 'line', 'description', 'sort_order', 'is_active'];

        $this->assertSame($expected, $machine->getFillable());
    }

    public function testHasCorrectCasts(): void
    {
        $machine = new Machine();
        $casts = $machine->getCasts();

        $this->assertSame('integer', $casts['sort_order']);
        $this->assertSame('boolean', $casts['is_active']);
    }

    public function testScopeForLineFiltersCorrectly(): void
    {
        Machine::create(['code' => 'test-dtf1', 'name' => 'T1', 'status' => 'online', 'department' => 'print', 'line' => 'dtf1', 'sort_order' => 1]);
        Machine::create(['code' => 'test-dtf2', 'name' => 'T2', 'status' => 'online', 'department' => 'print', 'line' => 'dtf2', 'sort_order' => 2]);

        $result = Machine::query()->forLine('dtf1')->get();

        $this->assertCount(1, $result);
        $this->assertSame('test-dtf1', $result->first()->code);
    }

    public function testScopeForDepartmentFiltersCorrectly(): void
    {
        Machine::create(['code' => 'test-print', 'name' => 'T1', 'status' => 'online', 'department' => 'print', 'line' => 'dtf1', 'sort_order' => 1]);
        Machine::create(['code' => 'test-cut', 'name' => 'T2', 'status' => 'online', 'department' => 'cut', 'line' => 'dtf1', 'sort_order' => 2]);

        $result = Machine::query()->forDepartment('cut')->get();

        $this->assertCount(1, $result);
        $this->assertSame('test-cut', $result->first()->code);
    }

    public function testScopeByStatusFiltersCorrectly(): void
    {
        Machine::create(['code' => 'test-on', 'name' => 'T1', 'status' => 'online', 'department' => 'print', 'line' => 'dtf1', 'sort_order' => 1]);
        Machine::create(['code' => 'test-off', 'name' => 'T2', 'status' => 'offline', 'department' => 'print', 'line' => 'dtf1', 'sort_order' => 2]);

        $result = Machine::query()->byStatus('offline')->get();

        $this->assertCount(1, $result);
        $this->assertSame('test-off', $result->first()->code);
    }

    public function testScopeActiveFiltersCorrectly(): void
    {
        Machine::create(['code' => 'test-active', 'name' => 'T1', 'status' => 'online', 'department' => 'print', 'line' => 'dtf1', 'sort_order' => 1, 'is_active' => true]);
        Machine::create(['code' => 'test-inactive', 'name' => 'T2', 'status' => 'online', 'department' => 'print', 'line' => 'dtf1', 'sort_order' => 2, 'is_active' => false]);

        $result = Machine::query()->active()->get();

        $this->assertCount(1, $result);
        $this->assertSame('test-active', $result->first()->code);
    }
}
