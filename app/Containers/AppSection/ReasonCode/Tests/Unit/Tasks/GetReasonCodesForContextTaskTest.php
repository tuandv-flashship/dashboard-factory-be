<?php

namespace App\Containers\AppSection\ReasonCode\Tests\Unit\Tasks;

use App\Containers\AppSection\ReasonCode\Models\ReasonCategory;
use App\Containers\AppSection\ReasonCode\Models\ReasonError;
use App\Containers\AppSection\ReasonCode\Models\ReasonSubItem;
use App\Containers\AppSection\ReasonCode\Tasks\GetReasonCodesForContextTask;
use App\Containers\AppSection\ReasonCode\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GetReasonCodesForContextTask::class)]
final class GetReasonCodesForContextTaskTest extends UnitTestCase
{
    public function testReturnsAllCategoriesWithFilteredSubItems(): void
    {
        $machine = ReasonCategory::create([
            'code' => 'machine', 'label' => 'Máy móc', 'label_en' => 'Machine',
            'icon' => 'Cog', 'color' => '#ef4444', 'sort_order' => 1,
        ]);
        $human = ReasonCategory::create([
            'code' => 'human', 'label' => 'Con người', 'label_en' => 'Human',
            'icon' => 'Users', 'color' => '#f59e0b', 'sort_order' => 2,
        ]);

        // Machine sub-item for dtf1/print
        ReasonSubItem::create([
            'category_id' => $machine->id, 'code' => 'machine-dtf1-print-dtf-01', 'label' => 'DTF-01',
            'scope_type' => 'per_line_department', 'scope_line' => 'dtf1', 'scope_dept' => 'print', 'sort_order' => 1,
        ]);
        // Human sub-item (global)
        ReasonSubItem::create([
            'category_id' => $human->id, 'code' => 'human-absent', 'label' => 'Vắng mặt',
            'scope_type' => 'global', 'sort_order' => 1,
        ]);
        // Machine error (common)
        ReasonError::create([
            'category_id' => $machine->id, 'code' => 'err-breakdown', 'label' => 'Hỏng máy',
            'sort_order' => 1,
        ]);

        $result = app(GetReasonCodesForContextTask::class)->run('dtf1', 'print');

        $this->assertCount(2, $result); // 2 categories
        // Machine category should have 1 sub-item (DTF-01 for dtf1/print) and 1 error
        $machineResult = $result->firstWhere('code', 'machine');
        $this->assertCount(1, $machineResult->subItems);
        $this->assertCount(1, $machineResult->errors);
        // Human category should have 1 global sub-item
        $humanResult = $result->firstWhere('code', 'human');
        $this->assertCount(1, $humanResult->subItems);
    }

    public function testFiltersSubItemsByContext(): void
    {
        $cat = ReasonCategory::create([
            'code' => 'machine', 'label' => 'Máy', 'label_en' => 'Machine',
            'icon' => 'Cog', 'color' => '#ef4444', 'sort_order' => 1,
        ]);
        ReasonSubItem::create([
            'category_id' => $cat->id, 'code' => 'dtf1-m', 'label' => 'DTF1 Machine',
            'scope_type' => 'per_line_department', 'scope_line' => 'dtf1', 'scope_dept' => 'print', 'sort_order' => 1,
        ]);
        ReasonSubItem::create([
            'category_id' => $cat->id, 'code' => 'dtf2-m', 'label' => 'DTF2 Machine',
            'scope_type' => 'per_line_department', 'scope_line' => 'dtf2', 'scope_dept' => 'print', 'sort_order' => 2,
        ]);

        // DTF1 context should only get dtf1 machine
        $result = app(GetReasonCodesForContextTask::class)->run('dtf1', 'print');
        $category = $result->first();
        $this->assertCount(1, $category->subItems);
        $this->assertSame('dtf1-m', $category->subItems->first()->code);
    }
}
