<?php

namespace App\Containers\AppSection\FplatformData\Tests\Unit\Support;

use App\Containers\AppSection\FplatformData\Support\DtgMachineSplitter;
use App\Containers\AppSection\FplatformData\Tests\UnitTestCase;

final class DtgMachineSplitterTest extends UnitTestCase
{
    public function testSplitsProportionallyAndLastTakesRemainder(): void
    {
        $r = DtgMachineSplitter::split(1000, ['apollo' => 250, 'atlas_1' => 75, 'atlas_2' => 75]);

        $this->assertSame(['ratio' => '62.5%', 'tong_viec' => 625], $r['apollo']);
        $this->assertSame(['ratio' => '18.75%', 'tong_viec' => 188], $r['atlas_1']);
        $this->assertSame(187, $r['atlas_2']['tong_viec']); // 1000 - 625 - 188
        $this->assertSame(1000, array_sum(array_column($r, 'tong_viec')));
    }

    public function testEmptyOrZeroKpiReturnsEmpty(): void
    {
        $this->assertSame([], DtgMachineSplitter::split(500, []));
        $this->assertSame([], DtgMachineSplitter::split(500, ['a' => 0]));
    }

    public function testSixMachinesSumMatchesAndKeepsOrder(): void
    {
        $r = DtgMachineSplitter::split(1234, [
            'apollo' => 280, 'atlas_1' => 90, 'atlas_2' => 98,
            'atlas4' => 90, 'atlas5' => 90, 'apollo6' => 280,
        ]);

        $this->assertCount(6, $r);
        $this->assertSame(1234, array_sum(array_column($r, 'tong_viec')));
        $this->assertSame(['apollo', 'atlas_1', 'atlas_2', 'atlas4', 'atlas5', 'apollo6'], array_keys($r));
        $this->assertSame('30.17%', $r['apollo']['ratio']); // 280 / 928
    }

    public function testZeroInventoryGivesZeroPerMachine(): void
    {
        $r = DtgMachineSplitter::split(0, ['apollo' => 280, 'atlas_1' => 90]);

        $this->assertSame(0, $r['apollo']['tong_viec']);
        $this->assertSame(0, $r['atlas_1']['tong_viec']);
    }
}
