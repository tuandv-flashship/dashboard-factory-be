<?php

namespace App\Containers\AppSection\ReasonCode\Tests\Unit\Models;

use App\Containers\AppSection\ReasonCode\Models\ReasonCategory;
use App\Containers\AppSection\ReasonCode\Models\ReasonError;
use App\Containers\AppSection\ReasonCode\Models\ReasonSubItem;
use App\Containers\AppSection\ReasonCode\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(ReasonCategory::class)]
#[CoversClass(ReasonSubItem::class)]
#[CoversClass(ReasonError::class)]
final class ReasonCodeModelsTest extends UnitTestCase
{
    private ReasonCategory $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->category = ReasonCategory::create([
            'code' => 'machine', 'label' => 'Máy móc', 'label_en' => 'Machine',
            'icon' => 'Cog', 'color' => '#ef4444', 'sort_order' => 1,
        ]);
    }

    public function testCategoryHasSubItems(): void
    {
        ReasonSubItem::create([
            'category_id' => $this->category->id, 'code' => 'sub-1', 'label' => 'Test',
            'scope_type' => 'global', 'sort_order' => 1,
        ]);

        $this->assertCount(1, $this->category->subItems);
    }

    public function testCategoryHasErrors(): void
    {
        ReasonError::create([
            'category_id' => $this->category->id, 'code' => 'err-1', 'label' => 'Test Error',
            'sort_order' => 1,
        ]);

        $this->assertCount(1, $this->category->errors);
    }

    public function testSubItemScopeForContextGlobal(): void
    {
        ReasonSubItem::create([
            'category_id' => $this->category->id, 'code' => 'global-1', 'label' => 'Global',
            'scope_type' => 'global', 'sort_order' => 1,
        ]);
        ReasonSubItem::create([
            'category_id' => $this->category->id, 'code' => 'dept-1', 'label' => 'Print Only',
            'scope_type' => 'per_department', 'scope_dept' => 'print', 'sort_order' => 2,
        ]);

        // Global items should appear for any context
        $result = ReasonSubItem::query()->forContext('dtf1', 'cut')->get();
        $this->assertCount(1, $result);
        $this->assertSame('global-1', $result->first()->code);

        // print dept should see both global + per_department
        $result = ReasonSubItem::query()->forContext('dtf1', 'print')->get();
        $this->assertCount(2, $result);
    }

    public function testSubItemScopeForContextPerLineDepartment(): void
    {
        ReasonSubItem::create([
            'category_id' => $this->category->id, 'code' => 'dtf1-print-m1', 'label' => 'DTF-01',
            'scope_type' => 'per_line_department', 'scope_line' => 'dtf1', 'scope_dept' => 'print', 'sort_order' => 1,
        ]);
        ReasonSubItem::create([
            'category_id' => $this->category->id, 'code' => 'dtf2-print-m1', 'label' => 'DTF-01 B2',
            'scope_type' => 'per_line_department', 'scope_line' => 'dtf2', 'scope_dept' => 'print', 'sort_order' => 2,
        ]);

        // DTF1 print should only see dtf1 machine
        $result = ReasonSubItem::query()->forContext('dtf1', 'print')->get();
        $this->assertCount(1, $result);
        $this->assertSame('dtf1-print-m1', $result->first()->code);

        // DTF2 print should only see dtf2 machine
        $result = ReasonSubItem::query()->forContext('dtf2', 'print')->get();
        $this->assertCount(1, $result);
        $this->assertSame('dtf2-print-m1', $result->first()->code);
    }

    public function testErrorScopeForDeptCommon(): void
    {
        ReasonError::create([
            'category_id' => $this->category->id, 'code' => 'err-common', 'label' => 'Common',
            'scope_dept' => null, 'sort_order' => 1,
        ]);
        ReasonError::create([
            'category_id' => $this->category->id, 'code' => 'err-print', 'label' => 'Print Only',
            'scope_dept' => 'print', 'sort_order' => 2,
        ]);

        // Print dept: common + print-specific
        $result = ReasonError::query()->forDept('print')->get();
        $this->assertCount(2, $result);

        // Cut dept: only common
        $result = ReasonError::query()->forDept('cut')->get();
        $this->assertCount(1, $result);
        $this->assertSame('err-common', $result->first()->code);
    }
}
