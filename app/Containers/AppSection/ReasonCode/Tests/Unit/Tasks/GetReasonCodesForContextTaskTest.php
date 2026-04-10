<?php

namespace App\Containers\AppSection\ReasonCode\Tests\Unit\Tasks;

use App\Containers\AppSection\ReasonCode\Models\ReasonCategory;
use App\Containers\AppSection\ReasonCode\Tasks\GetReasonCodesForContextTask;
use App\Containers\AppSection\ReasonCode\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GetReasonCodesForContextTask::class)]
final class GetReasonCodesForContextTaskTest extends UnitTestCase
{
    public function testReturnsAllCategoriesWithFilteredSubItems(): void
    {
        // Seeder provides all data — test against dtf/print context
        $result = app(GetReasonCodesForContextTask::class)->run('dtf', 'print');

        // Should return all 4 categories
        $this->assertCount(4, $result);

        // Machine category should have sub-items for dtf/print context
        $machineResult = $result->firstWhere('code', 'machine');
        $this->assertNotEmpty($machineResult->subItems, 'Machine should have sub-items for dtf/print');
        $this->assertNotEmpty($machineResult->errors, 'Machine should have errors');

        // Human category should have global sub-items
        $humanResult = $result->firstWhere('code', 'human');
        $this->assertNotEmpty($humanResult->subItems, 'Human should have global sub-items');
    }

    public function testFiltersSubItemsByContext(): void
    {
        // DTF context should only get dtf per_line_department items
        $result = app(GetReasonCodesForContextTask::class)->run('dtf', 'print');
        $machineCategory = $result->firstWhere('code', 'machine');

        foreach ($machineCategory->subItems as $subItem) {
            if ($subItem->scope_type === 'per_line_department') {
                $this->assertSame('dtf', $subItem->scope_line);
                $this->assertSame('print', $subItem->scope_dept);
            }
        }

        // DTG context should only get dtg per_line_department items
        $result2 = app(GetReasonCodesForContextTask::class)->run('dtg', 'print');
        $machineCategory2 = $result2->firstWhere('code', 'machine');

        foreach ($machineCategory2->subItems as $subItem) {
            if ($subItem->scope_type === 'per_line_department') {
                $this->assertSame('dtg', $subItem->scope_line);
                $this->assertSame('print', $subItem->scope_dept);
            }
        }
    }
}
