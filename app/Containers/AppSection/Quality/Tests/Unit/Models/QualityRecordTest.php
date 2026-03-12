<?php

namespace App\Containers\AppSection\Quality\Tests\Unit\Models;

use App\Containers\AppSection\Quality\Models\QualityRecord;
use App\Containers\AppSection\Quality\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(QualityRecord::class)]
final class QualityRecordTest extends UnitTestCase
{
    public function testUsesCorrectTable(): void
    {
        $this->assertSame('quality_records', (new QualityRecord())->getTable());
    }

    public function testHasCorrectFillableFields(): void
    {
        $expected = ['date', 'shift_number', 'pass_rate', 'inspected', 'passed', 'failed', 'avg_error_rate'];

        $this->assertSame($expected, (new QualityRecord())->getFillable());
    }

    public function testHasCorrectCasts(): void
    {
        $casts = (new QualityRecord())->getCasts();

        $this->assertSame('immutable_date', $casts['date']);
        $this->assertSame('integer', $casts['shift_number']);
        $this->assertSame('float', $casts['pass_rate']);
        $this->assertSame('integer', $casts['inspected']);
        $this->assertSame('integer', $casts['passed']);
        $this->assertSame('integer', $casts['failed']);
        $this->assertSame('float', $casts['avg_error_rate']);
    }

    public function testCurrentReturnsTodayLatestShift(): void
    {
        QualityRecord::create([
            'date' => now()->toDateString(), 'shift_number' => 1,
            'pass_rate' => 95.0, 'inspected' => 500, 'passed' => 475, 'failed' => 25, 'avg_error_rate' => 5.0,
        ]);
        QualityRecord::create([
            'date' => now()->toDateString(), 'shift_number' => 2,
            'pass_rate' => 98.1, 'inspected' => 1056, 'passed' => 1036, 'failed' => 20, 'avg_error_rate' => 1.9,
        ]);

        $current = QualityRecord::current();

        $this->assertNotNull($current);
        $this->assertSame(2, $current->shift_number); // latest shift_number
        $this->assertEquals(98.1, $current->pass_rate);
    }

    public function testCurrentReturnsNullWhenNoDataToday(): void
    {
        // Create data for yesterday only
        QualityRecord::create([
            'date' => now()->subDay()->toDateString(), 'shift_number' => 1,
            'pass_rate' => 95.0, 'inspected' => 500, 'passed' => 475, 'failed' => 25, 'avg_error_rate' => 5.0,
        ]);

        $this->assertNull(QualityRecord::current());
    }

    public function testCurrentIgnoresYesterdayData(): void
    {
        QualityRecord::create([
            'date' => now()->subDay()->toDateString(), 'shift_number' => 3,
            'pass_rate' => 99.0, 'inspected' => 2000, 'passed' => 1980, 'failed' => 20, 'avg_error_rate' => 1.0,
        ]);
        QualityRecord::create([
            'date' => now()->toDateString(), 'shift_number' => 1,
            'pass_rate' => 98.1, 'inspected' => 1056, 'passed' => 1036, 'failed' => 20, 'avg_error_rate' => 1.9,
        ]);

        $current = QualityRecord::current();

        $this->assertSame(1, $current->shift_number); // today's shift, not yesterday's
        $this->assertSame(1056, $current->inspected);
    }
}
