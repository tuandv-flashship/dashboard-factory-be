<?php

namespace App\Containers\AppSection\Production\Tests\Unit\Support;

use App\Containers\AppSection\Production\Support\DepartmentSummary;
use App\Containers\AppSection\Production\Tests\UnitTestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(DepartmentSummary::class)]
final class DepartmentSummaryTest extends UnitTestCase
{
    /**
     * Test kịch bản A: Bộ phận hoàn thành hết việc ngay trong ca.
     */
    public function testNormalScenarioA(): void
    {
        $records = collect([
            (object)[
                'hour_slot' => '13h-14h',
                'hour_start_inventory' => 150,
                'kpi_minutes' => 60,
            ],
            (object)[
                'hour_slot' => '14h-15h',
                'hour_start_inventory' => 40,
                'kpi_minutes' => 60,
            ],
            (object)[
                'hour_slot' => '15h-16h',
                'hour_start_inventory' => 0,
                'kpi_minutes' => 60,
            ],
        ]);

        $effectiveTargets = collect([100, 80, 80]);

        [$estimatedEndTime, $outOfWorkAt] = DepartmentSummary::computeEstimatedEndTime($records, $effectiveTargets);

        $this->assertSame('14:30', $estimatedEndTime);
        $this->assertSame('14h-15h', $outOfWorkAt);
    }

    /**
     * Test kịch bản B: Bộ phận bị quá tải, phải tính thêm giờ ngoài ca
     * dựa trên năng suất của khung giờ cuối cùng (target cuối > 0).
     */
    public function testOverloadScenarioBWithLastSlotTarget(): void
    {
        $records = collect([
            (object)[
                'hour_slot' => '13h-14h',
                'hour_start_inventory' => 150,
                'kpi_minutes' => 60,
            ],
            (object)[
                'hour_slot' => '14h-15h',
                'hour_start_inventory' => 110,
                'kpi_minutes' => 60,
            ],
        ]);

        $effectiveTargets = collect([100, 80]); // Tồn cuối slot 2: 110 - 80 = 30 đơn.
        // Năng suất khung giờ cuối: 80 đơn / 60 phút = 1.333 đơn/phút.
        // Thời gian làm thêm: ceil(30 / 1.333) = 23 phút.
        // Giờ kết thúc ca cũ: 14h + 60 phút = 15:00.
        // Giờ kết thúc mới: 15:23.

        [$estimatedEndTime, $outOfWorkAt] = DepartmentSummary::computeEstimatedEndTime($records, $effectiveTargets);

        $this->assertSame('15:23', $estimatedEndTime);
        $this->assertNull($outOfWorkAt);
    }

    /**
     * Test kịch bản B: Bộ phận bị quá tải, target của khung giờ cuối bằng 0,
     * hệ thống tự động fallback về năng suất định mức của bộ phận (fallbackCapacityPerHour).
     */
    public function testOverloadScenarioBWithZeroTargetFallbackCapacity(): void
    {
        $records = collect([
            (object)[
                'hour_slot' => '13h-14h',
                'hour_start_inventory' => 150,
                'kpi_minutes' => 60,
            ],
            (object)[
                'hour_slot' => '14h-15h',
                'hour_start_inventory' => 40,
                'kpi_minutes' => 60,
            ],
        ]);

        $effectiveTargets = collect([100, 0]); // Target khung giờ cuối bằng 0.
        // Tồn cuối slot 2: 40 - 0 = 40 đơn.
        // Năng suất định mức (fallback): 60 đơn / 60 phút = 1 đơn/phút.
        // Thời gian làm thêm: ceil(40 / 1) = 40 phút.
        // Giờ kết thúc ca cũ: 14h + 60 phút = 15:00.
        // Giờ kết thúc mới: 15:40.

        [$estimatedEndTime, $outOfWorkAt] = DepartmentSummary::computeEstimatedEndTime($records, $effectiveTargets, 60.0);

        $this->assertSame('15:40', $estimatedEndTime);
        $this->assertNull($outOfWorkAt);
    }

    /**
     * Test kịch bản B: Bộ phận bị quá tải, mọi năng suất (target cuối & fallback) đều bằng 0,
     * hệ thống tự động giữ nguyên giờ kết thúc ca.
     */
    public function testOverloadScenarioBWithZeroTargetAndNoFallback(): void
    {
        $records = collect([
            (object)[
                'hour_slot' => '13h-14h',
                'hour_start_inventory' => 150,
                'kpi_minutes' => 60,
            ],
            (object)[
                'hour_slot' => '14h-15h',
                'hour_start_inventory' => 40,
                'kpi_minutes' => 60,
            ],
        ]);

        $effectiveTargets = collect([100, 0]);

        [$estimatedEndTime, $outOfWorkAt] = DepartmentSummary::computeEstimatedEndTime($records, $effectiveTargets, 0.0);

        $this->assertSame('15:00', $estimatedEndTime);
        $this->assertNull($outOfWorkAt);
    }

    /**
     * Test kịch bản B qua đêm: Thời gian hoàn thành dự kiến vượt sang ngày hôm sau (+1 ngày).
     */
    public function testScenarioBOvernightWithDayRollover(): void
    {
        $records = collect([
            (object)[
                'hour_slot' => '22h-23h',
                'hour_start_inventory' => 150,
                'kpi_minutes' => 60,
            ],
        ]);

        $effectiveTargets = collect([50]);
        // Tồn cuối: 150 - 50 = 100 đơn.
        // Năng suất: 50 đơn/giờ (50/60 = 0.833 đơn/phút).
        // Phút bù thêm: ceil(100 / 0.833) = 120 phút (2 giờ).
        // Giờ kết thúc ca: 23:00.
        // Giờ dự kiến: 23:00 + 2h = 01:00 hôm sau.
        // Định dạng kỳ vọng: "01:00 + 1d"

        [$estimatedEndTime, $outOfWorkAt] = DepartmentSummary::computeEstimatedEndTime($records, $effectiveTargets);

        $this->assertSame('01:00 + 1d', $estimatedEndTime);
        $this->assertNull($outOfWorkAt);
    }

    /**
     * Test kịch bản B đặc biệt: Quá tải cực nặng, dự kiến xong sau 2 ngày (+2 ngày).
     */
    public function testScenarioBWithMultiDayRollover(): void
    {
        $records = collect([
            (object)[
                'hour_slot' => '22h-23h',
                'hour_start_inventory' => 2050,
                'kpi_minutes' => 60,
            ],
        ]);

        $effectiveTargets = collect([50]);
        // Tồn cuối: 2050 - 50 = 2000 đơn.
        // Năng suất: 50 đơn/giờ.
        // Phút bù thêm: ceil(2000 / 0.833) = 2400 phút (40 giờ).
        // Giờ kết thúc ca: 23:00 (1380 phút).
        // Tổng phút: 1380 + 2400 = 3780 phút.
        // Giờ tương ứng: 3780 / 60 = 63 giờ.
        // 63 giờ = 2 ngày (48 giờ) + 15 giờ.
        // Định dạng kỳ vọng: "15:00 + 2d"

        [$estimatedEndTime, $outOfWorkAt] = DepartmentSummary::computeEstimatedEndTime($records, $effectiveTargets);

        $this->assertSame('15:00 + 2d', $estimatedEndTime);
        $this->assertNull($outOfWorkAt);
    }

    /**
     * Test kịch bản thực tế (bộ phận Pick): Số nhân sự ca = 0 (headcount = 0),
     * target hiệu dụng khung giờ cuối = 0, nhưng có actual staff = 10 làm việc.
     * Hệ thống sẽ tự động dùng actual staff làm fallbackMultiplier để tính công suất thực tế.
     */
    public function testScenarioBOverloadWithActualStaffFallback(): void
    {
        $records = collect([
            (object)[
                'hour_slot' => '13h-14h',
                'hour_start_inventory' => 200,
                'target' => 0,
                'kpi_percent' => 100,
                'staff_required' => 0,
                'staff' => 8,
                'actual' => 100,
                'kpi_minutes' => 60,
                'hour_index' => 1,
                'status' => 'completed',
                'productivity_json' => null,
            ],
            (object)[
                'hour_slot' => '14h-15h',
                'hour_start_inventory' => 145,
                'target' => 0,
                'kpi_percent' => 100,
                'staff_required' => 0,
                'staff' => 10,
                'actual' => 100,
                'kpi_minutes' => 30, // 30 phút cuối ca
                'hour_index' => 2,
                'status' => 'completed',
                'productivity_json' => null,
            ],
        ]);

        $dept = new \App\Containers\AppSection\Department\Models\Department();
        $dept->productivity_type = \App\Containers\AppSection\Department\Enums\ProductivityType::PerPerson;
        $dept->kpi_per_hour = 180;

        $shiftDetail = new \App\Containers\AppSection\Shift\Models\ShiftDetail();
        $shiftDetail->headcount = 0;

        // Gọi build() để kích hoạt tính toán estimated_end_time từ đầu
        $summary = DepartmentSummary::build($records, $dept, $shiftDetail);

        // Năng suất định mức fallback: TargetEstimator::estimate(180, 100, false, 10) = 1800 đơn/giờ.
        // Tồn cuối: 145 - 0 (target hiệu dụng slot cuối) = 145 đơn.
        // Tốc độ: 1800 đơn / 30 phút = 60 đơn/phút. (Vì kpi_minutes slot cuối = 30).
        // Phút bù thêm: ceil(145 / 60) = 3 phút.
        // Giờ kết thúc ca: 14:00 + 30 phút = 14:30.
        // Giờ hoàn thành mới: 14:33.

        $this->assertSame('14:33', $summary['estimated_end_time']);
    }
}
