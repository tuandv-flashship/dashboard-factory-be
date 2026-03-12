<?php

namespace App\Containers\AppSection\Quality\Tests\Unit\Tasks;

use App\Containers\AppSection\Quality\Models\QualityRecord;
use App\Containers\AppSection\Quality\Tasks\GetQualityDataTask;
use App\Containers\AppSection\Quality\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GetQualityDataTask::class)]
final class GetQualityDataTaskTest extends UnitTestCase
{
    public function testReturnsCurrentQualityRecord(): void
    {
        QualityRecord::create([
            'date' => now()->toDateString(), 'shift_number' => 1,
            'pass_rate' => 98.1, 'inspected' => 1056, 'passed' => 1036, 'failed' => 20, 'avg_error_rate' => 1.9,
        ]);

        $result = app(GetQualityDataTask::class)->run();

        $this->assertNotNull($result);
        $this->assertInstanceOf(QualityRecord::class, $result);
        $this->assertEquals(98.1, $result->pass_rate);
        $this->assertSame(1056, $result->inspected);
    }

    public function testReturnsNullWhenNoDataExists(): void
    {
        $result = app(GetQualityDataTask::class)->run();

        $this->assertNull($result);
    }
}
