<?php

namespace App\Containers\AppSection\Production\Tests\Unit\Models;

use App\Containers\AppSection\Production\Models\Department;
use App\Containers\AppSection\Production\Models\HourlyIssue;
use App\Containers\AppSection\Production\Models\HourlyRecord;
use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Production\Models\Shift;
use App\Containers\AppSection\Production\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ProductionLine::class)]
#[CoversClass(Department::class)]
#[CoversClass(Shift::class)]
#[CoversClass(HourlyRecord::class)]
#[CoversClass(HourlyIssue::class)]
final class ProductionModelsTest extends UnitTestCase
{
    public function testProductionLineHasDepartments(): void
    {
        $line = ProductionLine::create(['code' => 'test_line1', 'label' => 'Test Line 1', 'color' => '#ff0000', 'sort_order' => 10]);
        Department::create([
            'production_line_id' => $line->id, 'code' => 'dept1',
            'label' => 'Dept 1', 'label_en' => 'Dept 1', 'icon' => 'Printer', 'unit' => 'file', 'sort_order' => 1,
        ]);

        $this->assertCount(1, $line->departments);
        $this->assertSame('dept1', $line->departments->first()->code);
    }

    public function testDepartmentBelongsToLine(): void
    {
        $line = ProductionLine::create(['code' => 'test_line2', 'label' => 'Test Line 2', 'color' => '#00ff00', 'sort_order' => 11]);
        $dept = Department::create([
            'production_line_id' => $line->id, 'code' => 'dept2',
            'label' => 'Dept 2', 'label_en' => 'Dept 2', 'icon' => 'Printer', 'unit' => 'file', 'sort_order' => 1,
        ]);

        $this->assertTrue($dept->productionLine->is($line));
    }

    public function testShiftCurrentReturnsLatestToday(): void
    {
        // Delete any existing shifts first (from migration seed)
        Shift::query()->delete();

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
        // Delete any existing shifts first (from migration seed)
        Shift::query()->delete();

        $this->assertNull(Shift::current());
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

    public function testPickDepartmentHourlyRecordWithIssues(): void
    {
        $pick = ProductionLine::create([
            'code' => 'test_pick', 'label' => 'Test Pick', 'color' => '#ec4899',
            'subtitle' => 'Lấy hàng', 'is_shared' => true, 'sort_order' => 14,
        ]);
        $dept = Department::create([
            'production_line_id' => $pick->id, 'code' => 'pick_dept1',
            'label' => 'Pick Dept 1', 'label_en' => 'Pick Dept 1',
            'icon' => 'ShoppingCart', 'unit' => 'shirt', 'kpi_per_hour' => 180,
            'factory' => 'FLS', 'sort_order' => 1,
        ]);
        $shift = Shift::create([
            'date' => '2099-01-03', 'shift_number' => 97,
            'start_time' => '06:00', 'end_time' => '14:00', 'supervisor' => 'Test', 'is_active' => true,
        ]);

        $record = HourlyRecord::create([
            'shift_id' => $shift->id, 'department_id' => $dept->id,
            'hour_slot' => '6h-7h', 'hour_index' => 0, 'target' => 160, 'actual' => 130,
            'staff' => 3, 'efficiency' => 92.0, 'error_rate' => 1.2,
        ]);

        HourlyIssue::create([
            'hourly_record_id' => $record->id,
            'category' => 'machine', 'sub_item' => 'Máy chính',
            'error' => 'Chạy chậm', 'note' => 'Giảm 30 so với KPI',
        ]);

        // Pick department uses same HourlyRecord model
        $this->assertTrue($record->department->is($dept));
        $this->assertTrue($dept->productionLine->is($pick));
        $this->assertTrue($pick->is_shared);
        $this->assertSame(180, $dept->kpi_per_hour);
        $this->assertSame('FLS', $dept->factory->value);

        // Pick supports issues
        $this->assertCount(1, $record->issues);
        $this->assertSame('machine', $record->issues->first()->category);
    }

    public function testProductionLineIsShared(): void
    {
        $line = ProductionLine::create([
            'code' => 'test_shared', 'label' => 'Test Shared', 'color' => '#ec4899',
            'is_shared' => true, 'sort_order' => 15,
        ]);

        $this->assertTrue($line->is_shared);
    }
}
