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

        // Use seeder-created machine category
        $this->category = ReasonCategory::where('code', 'machine')->first();
    }

    public function testCategoryHasSubItems(): void
    {
        $this->assertNotEmpty($this->category->subItems);
    }

    public function testCategoryHasErrors(): void
    {
        $this->assertNotEmpty($this->category->errors);
    }

    public function testSubItemScopeForContextGlobal(): void
    {
        // Global items should appear for any context
        $result = ReasonSubItem::query()->forContext('dtf1', 'cut')->get();
        $globalItems = $result->where('scope_type', 'global');
        $this->assertNotEmpty($globalItems);

        // per_department print items should NOT appear for cut context
        $printItems = $result->filter(fn ($item) => $item->scope_type === 'per_department' && $item->scope_dept !== 'cut');
        $this->assertEmpty($printItems);
    }

    public function testSubItemScopeForContextPerLineDepartment(): void
    {
        // DTF1 print should only see dtf1 per_line_department items
        $result = ReasonSubItem::query()->forContext('dtf1', 'print')->get();
        $perLineItems = $result->where('scope_type', 'per_line_department');

        foreach ($perLineItems as $item) {
            $this->assertSame('dtf1', $item->scope_line);
            $this->assertSame('print', $item->scope_dept);
        }

        // DTF2 print should only see dtf2 per_line_department items
        $result2 = ReasonSubItem::query()->forContext('dtf2', 'print')->get();
        $perLineItems2 = $result2->where('scope_type', 'per_line_department');

        foreach ($perLineItems2 as $item) {
            $this->assertSame('dtf2', $item->scope_line);
            $this->assertSame('print', $item->scope_dept);
        }
    }

    public function testErrorScopeForDeptCommon(): void
    {
        // Print dept: common (null) + print-specific
        $result = ReasonError::query()->forDept('print')->get();
        $this->assertNotEmpty($result);

        // All results should be either null dept or 'print' dept
        foreach ($result as $error) {
            $this->assertTrue(
                $error->scope_dept === null || $error->scope_dept === 'print',
                "Expected scope_dept to be null or 'print', got '{$error->scope_dept}'"
            );
        }

        // Cut dept should NOT include print-specific errors
        $cutResult = ReasonError::query()->forDept('cut')->get();
        foreach ($cutResult as $error) {
            $this->assertTrue(
                $error->scope_dept === null || $error->scope_dept === 'cut',
                "Expected scope_dept to be null or 'cut', got '{$error->scope_dept}'"
            );
        }
    }
}
