<?php

namespace App\Containers\AppSection\Alert\Tests\Unit\Models;

use App\Containers\AppSection\Alert\Models\Alert;
use App\Containers\AppSection\Alert\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(Alert::class)]
final class AlertTest extends UnitTestCase
{
    public function testUsesCorrectTable(): void
    {
        $this->assertSame('alerts', (new Alert())->getTable());
    }

    public function testHasCorrectFillableFields(): void
    {
        $expected = ['severity', 'department', 'time', 'message', 'line', 'is_resolved', 'resolved_at'];

        $this->assertSame($expected, (new Alert())->getFillable());
    }

    public function testScopeUnresolvedFiltersCorrectly(): void
    {
        Alert::create(['severity' => 'critical', 'department' => 'Print', 'time' => '10:00', 'message' => 'Active', 'line' => 'dtf1', 'is_resolved' => false]);
        Alert::create(['severity' => 'info', 'department' => 'Print', 'time' => '09:00', 'message' => 'Resolved', 'line' => 'dtf1', 'is_resolved' => true]);

        $result = Alert::query()->unresolved()->get();

        $this->assertCount(1, $result);
        $this->assertSame('Active', $result->first()->message);
    }

    public function testScopeForLineIncludesAllAlerts(): void
    {
        Alert::create(['severity' => 'critical', 'department' => 'Print', 'time' => '10:00', 'message' => 'DTF1 alert', 'line' => 'dtf1']);
        Alert::create(['severity' => 'info', 'department' => 'QC', 'time' => '09:00', 'message' => 'All alert', 'line' => 'all']);
        Alert::create(['severity' => 'warning', 'department' => 'Cut', 'time' => '08:00', 'message' => 'DTF2 alert', 'line' => 'dtf2']);

        // DTF1 should see dtf1 + all
        $result = Alert::query()->forLine('dtf1')->get();
        $this->assertCount(2, $result);

        // DTF2 should see dtf2 + all
        $result = Alert::query()->forLine('dtf2')->get();
        $this->assertCount(2, $result);
    }

    public function testScopeBySeverityFiltersCorrectly(): void
    {
        Alert::create(['severity' => 'critical', 'department' => 'Print', 'time' => '10:00', 'message' => 'Critical', 'line' => 'dtf1']);
        Alert::create(['severity' => 'warning', 'department' => 'Print', 'time' => '09:00', 'message' => 'Warning', 'line' => 'dtf1']);

        $result = Alert::query()->bySeverity('critical')->get();

        $this->assertCount(1, $result);
        $this->assertSame('Critical', $result->first()->message);
    }
}
