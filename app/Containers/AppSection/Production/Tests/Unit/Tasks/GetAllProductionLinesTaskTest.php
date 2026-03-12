<?php

namespace App\Containers\AppSection\Production\Tests\Unit\Tasks;

use App\Containers\AppSection\Production\Models\ProductionLine;
use App\Containers\AppSection\Production\Tasks\GetAllProductionLinesTask;
use App\Containers\AppSection\Production\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GetAllProductionLinesTask::class)]
final class GetAllProductionLinesTaskTest extends UnitTestCase
{
    public function testReturnsActiveLinesSortedBySortOrder(): void
    {
        ProductionLine::create(['code' => 'dtg', 'label' => 'DTG', 'color' => '#8b5cf6', 'sort_order' => 3]);
        ProductionLine::create(['code' => 'dtf1', 'label' => 'DTF 1', 'color' => '#f59e0b', 'sort_order' => 1]);
        ProductionLine::create(['code' => 'dtf2', 'label' => 'DTF 2', 'color' => '#14b8a6', 'sort_order' => 2, 'is_active' => false]);

        $result = app(GetAllProductionLinesTask::class)->run();

        $this->assertCount(2, $result); // dtf2 is inactive
        $this->assertSame('dtf1', $result->first()->code);
        $this->assertSame('dtg', $result->last()->code);
    }

    public function testEagerLoadsDepartments(): void
    {
        $line = ProductionLine::create(['code' => 'dtf1', 'label' => 'DTF 1', 'color' => '#f59e0b', 'sort_order' => 1]);
        \App\Containers\AppSection\Production\Models\Department::create([
            'production_line_id' => $line->id, 'code' => 'print',
            'label' => 'In ấn', 'label_en' => 'Print', 'icon' => 'Printer', 'unit' => 'files', 'sort_order' => 1,
        ]);

        $result = app(GetAllProductionLinesTask::class)->run();

        $this->assertTrue($result->first()->relationLoaded('departments'));
        $this->assertCount(1, $result->first()->departments);
    }
}
