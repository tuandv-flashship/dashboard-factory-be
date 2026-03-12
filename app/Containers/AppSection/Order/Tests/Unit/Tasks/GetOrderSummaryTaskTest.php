<?php

namespace App\Containers\AppSection\Order\Tests\Unit\Tasks;

use App\Containers\AppSection\Order\Models\OrderSummary;
use App\Containers\AppSection\Order\Tasks\GetOrderSummaryTask;
use App\Containers\AppSection\Order\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GetOrderSummaryTask::class)]
final class GetOrderSummaryTaskTest extends UnitTestCase
{
    public function testReturnsTotalAndPerLine(): void
    {
        $today = now()->toDateString();

        OrderSummary::create([
            'date' => $today, 'shift_number' => 1, 'line' => null,
            'total' => 1850, 'completed' => 1056, 'remaining' => 794,
            'estimated_done' => '16:30', 'progress' => 57,
        ]);
        OrderSummary::create([
            'date' => $today, 'shift_number' => 1, 'line' => 'dtf1', 'line_label' => 'DTF 1',
            'total' => 748, 'completed' => 423, 'remaining' => 325,
            'estimated_done' => '15:45', 'progress' => 57,
        ]);
        OrderSummary::create([
            'date' => $today, 'shift_number' => 1, 'line' => 'dtf2', 'line_label' => 'DTF 2',
            'total' => 620, 'completed' => 362, 'remaining' => 258,
            'estimated_done' => '16:00', 'progress' => 58,
        ]);

        $result = app(GetOrderSummaryTask::class)->run();

        $this->assertNotNull($result['total']);
        $this->assertSame(1850, $result['total']->total);
        $this->assertCount(2, $result['per_line']);
        $this->assertSame('dtf1', $result['per_line']->first()->line);
    }

    public function testReturnsNullTotalWhenNoData(): void
    {
        $result = app(GetOrderSummaryTask::class)->run();

        $this->assertNull($result['total']);
        $this->assertCount(0, $result['per_line']);
    }
}
