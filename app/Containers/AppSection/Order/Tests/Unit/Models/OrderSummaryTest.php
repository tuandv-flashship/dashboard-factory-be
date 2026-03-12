<?php

namespace App\Containers\AppSection\Order\Tests\Unit\Models;

use App\Containers\AppSection\Order\Models\OrderSummary;
use App\Containers\AppSection\Order\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(OrderSummary::class)]
final class OrderSummaryTest extends UnitTestCase
{
    public function testUsesCorrectTable(): void
    {
        $this->assertSame('order_summaries', (new OrderSummary())->getTable());
    }

    public function testScopeTotalReturnsLineNull(): void
    {
        OrderSummary::create([
            'date' => now()->toDateString(), 'shift_number' => 1, 'line' => null,
            'total' => 1850, 'completed' => 1056, 'remaining' => 794,
            'estimated_done' => '16:30', 'progress' => 57,
        ]);
        OrderSummary::create([
            'date' => now()->toDateString(), 'shift_number' => 1, 'line' => 'dtf1', 'line_label' => 'DTF 1',
            'total' => 748, 'completed' => 423, 'remaining' => 325,
            'estimated_done' => '15:45', 'progress' => 57,
        ]);

        $result = OrderSummary::query()->total()->get();

        $this->assertCount(1, $result);
        $this->assertNull($result->first()->line);
        $this->assertSame(1850, $result->first()->total);
    }

    public function testScopePerLineReturnsOrderedByLine(): void
    {
        OrderSummary::create([
            'date' => now()->toDateString(), 'shift_number' => 1, 'line' => 'dtg', 'line_label' => 'DTG',
            'total' => 482, 'completed' => 271, 'remaining' => 211,
            'estimated_done' => '16:30', 'progress' => 56,
        ]);
        OrderSummary::create([
            'date' => now()->toDateString(), 'shift_number' => 1, 'line' => 'dtf1', 'line_label' => 'DTF 1',
            'total' => 748, 'completed' => 423, 'remaining' => 325,
            'estimated_done' => '15:45', 'progress' => 57,
        ]);
        OrderSummary::create([
            'date' => now()->toDateString(), 'shift_number' => 1, 'line' => null,
            'total' => 1850, 'completed' => 1056, 'remaining' => 794,
            'estimated_done' => '16:30', 'progress' => 57,
        ]);

        $result = OrderSummary::query()->perLine()->get();

        $this->assertCount(2, $result); // excludes total (null)
        $this->assertSame('dtf1', $result->first()->line); // dtf1 before dtg
        $this->assertSame('dtg', $result->last()->line);
    }

    public function testScopeForShiftFiltersCorrectly(): void
    {
        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();
        OrderSummary::create([
            'date' => $today, 'shift_number' => 1, 'line' => null,
            'total' => 100, 'completed' => 50, 'remaining' => 50,
            'estimated_done' => '16:00', 'progress' => 50,
        ]);
        OrderSummary::create([
            'date' => $yesterday, 'shift_number' => 1, 'line' => null,
            'total' => 200, 'completed' => 100, 'remaining' => 100,
            'estimated_done' => '16:00', 'progress' => 50,
        ]);

        $result = OrderSummary::query()->forShift($today, 1)->get();

        $this->assertCount(1, $result);
        $this->assertSame(100, $result->first()->total);
    }
}
