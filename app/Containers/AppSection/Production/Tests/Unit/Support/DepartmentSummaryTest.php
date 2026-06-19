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
     * Truyền deptEndMinutes = 15:00 (15*60 = 900).
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
        // Giờ kết thúc bộ phận: 15:00 (deptEndMinutes = 900).
        // Giờ kết thúc mới: 15:00 + 23 phút = 15:23.

        [$estimatedEndTime, $outOfWorkAt] = DepartmentSummary::computeEstimatedEndTime(
            $records, $effectiveTargets, null, null, 15 * 60
        );

        $this->assertSame('15:23', $estimatedEndTime);
        $this->assertNull($outOfWorkAt);
    }

    /**
     * Test kịch bản B: Slot cuối có break time (kpi_minutes < 60 nhưng wall-clock = 60).
     * Ví dụ: slot 14h-15h có 15 phút break → kpi_minutes = 45.
     * Extra minutes phải cộng vào 15:00 (dept end) chứ không phải 14:45.
     */
    public function testOverloadScenarioBWithBreakTimeInLastSlot(): void
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
                'kpi_minutes' => 45, // 15 phút break trong slot
            ],
        ]);

        $effectiveTargets = collect([100, 60]); // Target giảm do break, tồn cuối: 110 - 60 = 50 đơn.
        // Năng suất khung giờ cuối: 60 đơn / 45 phút = 1.333 đơn/phút.
        // Thời gian làm thêm: ceil(50 / 1.333) = 38 phút.
        // Giờ kết thúc bộ phận: 15:00 (deptEndMinutes = 900).
        // Giờ kết thúc mới: 15:00 + 38 phút = 15:38.
        //
        // TRƯỚC KHI FIX: 14*60 + 45 + 38 = 923 phút = 15:23 (SAI, thiếu 15 phút break).

        [$estimatedEndTime, $outOfWorkAt] = DepartmentSummary::computeEstimatedEndTime(
            $records, $effectiveTargets, null, null, 15 * 60
        );

        $this->assertSame('15:38', $estimatedEndTime);
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
        // Giờ kết thúc bộ phận: 15:00 (deptEndMinutes = 900).
        // Giờ kết thúc mới: 15:00 + 40 = 15:40.

        [$estimatedEndTime, $outOfWorkAt] = DepartmentSummary::computeEstimatedEndTime(
            $records, $effectiveTargets, 60.0, null, 15 * 60
        );

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

        $this->assertNull($estimatedEndTime);
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
        // Giờ kết thúc bộ phận: 23:00 (deptEndMinutes = 1380).
        // Giờ dự kiến: 23:00 + 2h = 01:00 hôm sau.
        // Định dạng kỳ vọng: "01:00 + 1d"

        [$estimatedEndTime, $outOfWorkAt] = DepartmentSummary::computeEstimatedEndTime(
            $records, $effectiveTargets, null, null, 23 * 60
        );

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
        // Giờ kết thúc bộ phận: 23:00 (deptEndMinutes = 1380).
        // Tổng phút: 1380 + 2400 = 3780 phút.
        // Giờ tương ứng: 3780 / 60 = 63 giờ.
        // 63 giờ = 2 ngày (48 giờ) + 15 giờ.
        // Định dạng kỳ vọng: "15:00 + 2d"

        [$estimatedEndTime, $outOfWorkAt] = DepartmentSummary::computeEstimatedEndTime(
            $records, $effectiveTargets, null, null, 23 * 60
        );

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
        $shiftDetail->day_start_inventory = 345;

        // Set start_time so ShiftDetail::end_time accessor works
        // start_time=13:00, work_hours=1.5h (90 min), meal_break=0 → end_time=14:30
        $shiftDetail->start_time = '13:00:00';
        $shiftDetail->work_hours = 1.5;
        $shiftDetail->meal_break_minutes = 0;

        // Gọi build() để kích hoạt tính toán estimated_end_time từ đầu
        $summary = DepartmentSummary::build($records, $dept, $shiftDetail);

        // Năng suất định mức fallback: TargetEstimator::estimate(180, 100, false, 10) = 1800 đơn/giờ.
        // Tồn cuối: 145 - 0 (target hiệu dụng slot cuối) = 145 đơn.
        // Tốc độ: 1800 đơn / 60 phút = 30 đơn/phút. (Vì fallbackCapacityPerHour luôn chia cho 60 phút).
        // Phút bù thêm: ceil(145 / 30) = 5 phút.
        // Giờ kết thúc bộ phận (ShiftDetail::end_time): 14:30 (deptEndMinutes = 870).
        // Giờ hoàn thành mới: 14:30 + 5 phút = 14:35.

        $this->assertSame('14:35', $summary['estimated_end_time']);
    }
}
