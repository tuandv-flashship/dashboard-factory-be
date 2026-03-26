<?php

namespace App\Containers\AppSection\Department\Tests\Unit\Models;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Department\Tests\UnitTestCase;
use App\Containers\AppSection\Production\Models\ProductionLine;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Department::class)]
final class DepartmentModelsTest extends UnitTestCase
{
    public function testDepartmentBelongsToLine(): void
    {
        $line = ProductionLine::create(['code' => 'test_line2', 'label' => 'Test Line 2', 'color' => '#00ff00', 'sort_order' => 11]);
        $dept = Department::create([
            'production_line_id' => $line->id, 'code' => 'dept2',
            'label' => 'Dept 2', 'label_en' => 'Dept 2', 'icon' => 'Printer', 'unit' => 'file', 'sort_order' => 1,
        ]);

        $this->assertTrue($dept->productionLine->is($line));
    }

    public function testDepartmentHasHourlyRecords(): void
    {
        $line = ProductionLine::create(['code' => 'test_line_hr', 'label' => 'Test Line HR', 'color' => '#0000ff', 'sort_order' => 20]);
        $dept = Department::create([
            'production_line_id' => $line->id, 'code' => 'dept_hr',
            'label' => 'Dept HR', 'label_en' => 'Dept HR', 'icon' => 'Printer', 'unit' => 'file', 'sort_order' => 1,
        ]);

        $this->assertCount(0, $dept->hourlyRecords);
    }

    public function testDepartmentCastsEnums(): void
    {
        $line = ProductionLine::create(['code' => 'test_line_enum', 'label' => 'Test Line Enum', 'color' => '#ffff00', 'sort_order' => 21]);
        $dept = Department::create([
            'production_line_id' => $line->id, 'code' => 'dept_enum',
            'label' => 'Dept Enum', 'label_en' => 'Dept Enum', 'icon' => 'Printer',
            'unit' => 'shirt', 'kpi_per_hour' => 180, 'factory' => 'FLS', 'sort_order' => 1,
        ]);

        $this->assertSame(180, $dept->kpi_per_hour);
        $this->assertSame('FLS', $dept->factory->value);
        $this->assertSame('shirt', $dept->unit->value);
    }

    public function testPickDepartmentAttributes(): void
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

        $this->assertTrue($dept->productionLine->is($pick));
        $this->assertTrue($pick->is_shared);
        $this->assertSame(180, $dept->kpi_per_hour);
        $this->assertSame('FLS', $dept->factory->value);
    }
}
