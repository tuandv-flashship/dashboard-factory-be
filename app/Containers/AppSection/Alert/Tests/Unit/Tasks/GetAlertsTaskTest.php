<?php

namespace App\Containers\AppSection\Alert\Tests\Unit\Tasks;

use App\Containers\AppSection\Alert\Models\Alert;
use App\Containers\AppSection\Alert\Tasks\GetAlertsTask;
use App\Containers\AppSection\Alert\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(GetAlertsTask::class)]
final class GetAlertsTaskTest extends UnitTestCase
{
    public function testReturnsUnresolvedAlertsSortedByTimeDesc(): void
    {
        Alert::create(['severity' => 'info', 'department' => 'Print', 'time' => '08:00', 'message' => 'Early', 'line' => 'dtf1']);
        Alert::create(['severity' => 'critical', 'department' => 'Print', 'time' => '10:00', 'message' => 'Late', 'line' => 'dtf1']);
        Alert::create(['severity' => 'warning', 'department' => 'Print', 'time' => '09:00', 'message' => 'Resolved', 'line' => 'dtf1', 'is_resolved' => true]);

        $result = app(GetAlertsTask::class)->run();

        $this->assertCount(2, $result); // resolved excluded
        $this->assertSame('Late', $result->first()->message); // sorted desc by time
    }

    public function testFiltersAlertsByLine(): void
    {
        Alert::create(['severity' => 'critical', 'department' => 'Print', 'time' => '10:00', 'message' => 'DTF1', 'line' => 'dtf1']);
        Alert::create(['severity' => 'info', 'department' => 'QC', 'time' => '09:00', 'message' => 'ALL', 'line' => 'all']);
        Alert::create(['severity' => 'warning', 'department' => 'Cut', 'time' => '08:00', 'message' => 'DTF2', 'line' => 'dtf2']);

        $result = app(GetAlertsTask::class)->run('dtf1');

        $this->assertCount(2, $result); // dtf1 + all
        $this->assertTrue($result->contains('message', 'DTF1'));
        $this->assertTrue($result->contains('message', 'ALL'));
        $this->assertFalse($result->contains('message', 'DTF2'));
    }

    public function testReturnsAllWhenNoLineFilter(): void
    {
        Alert::create(['severity' => 'critical', 'department' => 'Print', 'time' => '10:00', 'message' => 'DTF1', 'line' => 'dtf1']);
        Alert::create(['severity' => 'warning', 'department' => 'Cut', 'time' => '08:00', 'message' => 'DTF2', 'line' => 'dtf2']);

        $result = app(GetAlertsTask::class)->run(null);

        $this->assertCount(2, $result);
    }
}
