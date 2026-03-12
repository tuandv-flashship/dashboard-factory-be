<?php

namespace App\Containers\AppSection\Production\Tests\Unit\Models;

use App\Containers\AppSection\Production\Models\Department;
use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Models\PickHourlyRecord;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Production\Models\Shift;
use App\Containers\AppSection\Production\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProductionLine::class)]
#[CoversClass(Department::class)]
#[CoversClass(Shift::class)]
#[CoversClass(HourlyRecord::class)]
#[CoversClass(HourlyIssue::class)]
#[CoversClass(PickHourlyRecord::class)]
final class ProductionModelsTest extends UnitTestCase
{
    public function testProductionLineHasDepartments(): void
    {
        $line = ProductionLine::create(['code' => 'dtf1', 'label' => 'DTF 1', 'color' => '#f59e0b', 'sort_order' => 1]);
        Department::create([
            'production_line_id' => $line->id, 'code' => 'print',
            'label' => 'In ấn', 'label_en' => 'Print', 'icon' => 'Printer', 'unit' => 'files', 'sort_order' => 1,
        ]);

        $this->assertCount(1, $line->departments);
        $this->assertSame('print', $line->departments->first()->code);
    }

    public function testDepartmentBelongsToLine(): void
    {
        $line = ProductionLine::create(['code' => 'dtf1', 'label' => 'DTF 1', 'color' => '#f59e0b', 'sort_order' => 1]);
        $dept = Department::create([
            'production_line_id' => $line->id, 'code' => 'print',
            'label' => 'In ấn', 'label_en' => 'Print', 'icon' => 'Printer', 'unit' => 'files', 'sort_order' => 1,
        ]);

        $this->assertTrue($dept->productionLine->is($line));
    }

    public function testShiftCurrentReturnsLatestToday(): void
    {
        Shift::create([
            'date' => now()->toDateString(), 'shift_number' => 1,
            'start_time' => '06:00', 'end_time' => '14:00', 'supervisor' => 'Test', 'is_active' => true,
        ]);

        $current = Shift::current();

        $this->assertNotNull($current);
        $this->assertSame(1, $current->shift_number);
    }

    public function testShiftCurrentReturnsNullWhenNone(): void
    {
        // No shifts created
        $this->assertNull(Shift::current());
    }

    public function testHourlyRecordHasIssues(): void
    {
        $line = ProductionLine::create(['code' => 'dtf1', 'label' => 'DTF 1', 'color' => '#f59e0b', 'sort_order' => 1]);
        $dept = Department::create([
            'production_line_id' => $line->id, 'code' => 'print',
            'label' => 'In', 'label_en' => 'Print', 'icon' => 'Printer', 'unit' => 'files', 'sort_order' => 1,
        ]);
        $shift = Shift::create([
            'date' => now()->toDateString(), 'shift_number' => 1,
            'start_time' => '06:00', 'end_time' => '14:00', 'supervisor' => 'Test', 'is_active' => true,
        ]);
        $record = HourlyRecord::create([
            'shift_id' => $shift->id, 'department_id' => $dept->id,
            'hour_slot' => '6h-7h', 'hour_index' => 0, 'target' => 100, 'actual' => 85,
            'staff' => 10, 'efficiency' => 85.0, 'error_rate' => 2.0,
        ]);
        HourlyIssue::create([
            'hourly_record_id' => $record->id,
            'category' => 'machine', 'sub_item' => 'DTF-01', 'error' => 'Chạy chậm', 'note' => 'Test',
        ]);

        $this->assertCount(1, $record->issues);
        $this->assertSame('machine', $record->issues->first()->category);
    }

    public function testHourlyRecordBelongsToShiftAndDepartment(): void
    {
        $line = ProductionLine::create(['code' => 'dtf1', 'label' => 'DTF 1', 'color' => '#f59e0b', 'sort_order' => 1]);
        $dept = Department::create([
            'production_line_id' => $line->id, 'code' => 'print',
            'label' => 'In', 'label_en' => 'Print', 'icon' => 'Printer', 'unit' => 'files', 'sort_order' => 1,
        ]);
        $shift = Shift::create([
            'date' => now()->toDateString(), 'shift_number' => 1,
            'start_time' => '06:00', 'end_time' => '14:00', 'supervisor' => 'Test', 'is_active' => true,
        ]);
        $record = HourlyRecord::create([
            'shift_id' => $shift->id, 'department_id' => $dept->id,
            'hour_slot' => '6h-7h', 'hour_index' => 0, 'target' => 100, 'actual' => 95,
            'staff' => 10, 'efficiency' => 95.0, 'error_rate' => 1.0,
        ]);

        $this->assertTrue($record->shift->is($shift));
        $this->assertTrue($record->department->is($dept));
    }

    public function testPickHourlyRecordBelongsToShiftAndLine(): void
    {
        $line = ProductionLine::create(['code' => 'dtf1', 'label' => 'DTF 1', 'color' => '#f59e0b', 'sort_order' => 1]);
        $shift = Shift::create([
            'date' => now()->toDateString(), 'shift_number' => 1,
            'start_time' => '06:00', 'end_time' => '14:00', 'supervisor' => 'Test', 'is_active' => true,
        ]);
        $pick = PickHourlyRecord::create([
            'shift_id' => $shift->id, 'production_line_id' => $line->id,
            'hour_slot' => '6h-7h', 'hour_index' => 0, 'target' => 50, 'actual' => 48,
            'staff' => 3, 'efficiency' => 92.0, 'error_rate' => 1.5, 'total_picked' => 48,
        ]);

        $this->assertTrue($pick->shift->is($shift));
        $this->assertTrue($pick->productionLine->is($line));
    }
}
