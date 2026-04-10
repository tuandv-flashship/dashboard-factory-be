<?php

namespace App\Containers\AppSection\Production\Tests\Unit\Models;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Production\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProductionLine::class)]
#[CoversClass(HourlyRecord::class)]
#[CoversClass(HourlyIssue::class)]
final class ProductionModelsTest extends UnitTestCase
{
    public function testProductionLineCreation(): void
    {
        $line = ProductionLine::create([
            'code' => 'test_line', 'label' => 'Test Line', 'color' => '#ec4899',
            'sort_order' => 15,
        ]);

        $this->assertSame('test_line', $line->code);
        $this->assertTrue($line->is_active);
    }

    public function testHourlyRecordHasIssues(): void
    {
        $line = ProductionLine::create(['code' => 'test_line3', 'label' => 'Test Line 3', 'color' => '#0000ff', 'sort_order' => 12]);
        $dept = Department::create([
            'production_line_id' => $line->id, 'code' => 'dept3',
            'label' => 'Dept 3', 'label_en' => 'Dept 3', 'icon' => 'Printer', 'unit' => 'file', 'sort_order' => 1,
        ]);
        $shift = Shift::create([
            'date' => '2099-01-01', 'shift_number' => 99,
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
        $line = ProductionLine::create(['code' => 'test_line4', 'label' => 'Test Line 4', 'color' => '#ff00ff', 'sort_order' => 13]);
        $dept = Department::create([
            'production_line_id' => $line->id, 'code' => 'dept4',
            'label' => 'Dept 4', 'label_en' => 'Dept 4', 'icon' => 'Printer', 'unit' => 'file', 'sort_order' => 1,
        ]);
        $shift = Shift::create([
            'date' => '2099-01-02', 'shift_number' => 98,
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
}
