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
            'unit' => 'shirt', 'kpi_per_hour' => 180, 'sort_order' => 1,
        ]);

        $this->assertSame(180, $dept->kpi_per_hour);
        $this->assertSame('shirt', $dept->unit->value);
    }

    public function testPickDepartmentAttributes(): void
    {
        $line = ProductionLine::create([
            'code' => 'test_dtf', 'label' => 'Test DTF', 'color' => '#ec4899',
            'subtitle' => 'Building 1', 'sort_order' => 14,
        ]);
        $dept = Department::create([
            'production_line_id' => $line->id, 'code' => 'pick',
            'label' => 'Pick', 'label_en' => 'Pick',
            'icon' => 'ShoppingCart', 'unit' => 'shirt', 'kpi_per_hour' => 180,
            'sort_order' => 1,
        ]);

        $this->assertTrue($dept->productionLine->is($line));
        $this->assertSame(180, $dept->kpi_per_hour);
    }
}
