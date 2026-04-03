<?php

namespace App\Containers\AppSection\Shift\UI\API\Transformers;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class ShiftDetailTransformer extends ParentTransformer
{
    protected array $defaultIncludes = [];
    protected array $availableIncludes = [];

    public function transform(ShiftDetail $detail): array
    {
        /** @var Department|null $dept */
        $dept = $detail->relationLoaded('department') ? $detail->department : null;
        $line = $dept?->relationLoaded('productionLine') ? $dept->productionLine : null;

        return [
            'id'                 => $detail->getHashedKey(),
            'department_id'      => $dept?->getHashedKey(),
            'department_code'    => $dept?->code,
            'department_label'   => $dept?->label,
            'line_code'          => $line?->code,
            'line_label'         => $line?->label,
            'shift_number'       => $detail->shift_number,
            'headcount'          => $detail->headcount,
            'kpi_per_hour'       => $detail->kpi_per_hour ?: ($detail->department?->kpi_per_hour ?? 0),
            'day_start_inventory'=> $detail->day_start_inventory,
            'start_time'         => $detail->start_time ? substr($detail->start_time, 0, 5) : null,
            'end_time'           => $detail->end_time,
            'work_hours'         => (float) $detail->work_hours,
            'prep_minutes'       => $detail->prep_minutes,
            'break1_start'       => $detail->break1_start ? substr($detail->break1_start, 0, 5) : null,
            'break1_minutes'     => $detail->break1_minutes,
            'meal_break_start'   => $detail->meal_break_start ? substr($detail->meal_break_start, 0, 5) : null,
            'meal_break_minutes' => $detail->meal_break_minutes,
            'break2_start'       => $detail->break2_start ? substr($detail->break2_start, 0, 5) : null,
            'break2_minutes'     => $detail->break2_minutes,
            'break3_start'       => $detail->break3_start ? substr($detail->break3_start, 0, 5) : null,
            'break3_minutes'     => $detail->break3_minutes,
            'kpi_hours'          => $this->computeKpiHours($detail),
            'required_headcount' => $this->computeRequiredHeadcount($detail),
        ];
    }


    /**
     * Số giờ tính KPI = work_hours - prep_minutes/60
     *                 - Tổng thời gian nghỉ giải lao nằm trong ca
     */
    private function computeKpiHours(ShiftDetail $detail): float
    {
        $workHours  = (float) $detail->work_hours;
        $prepHours  = (int) $detail->prep_minutes / 60;
        $breakHours = $this->computeBreakHoursWithinShift($detail);

        return max(0.0, $workHours - $prepHours - $breakHours);
    }

    /**
     * Tính số nhân sự yêu cầu:
     *   Số nhân sự = RoundUp( day_start_inventory / kpi_per_hour / kpi_hours )
     */
    private function computeRequiredHeadcount(ShiftDetail $detail): int
    {
        $kpiHours   = $this->computeKpiHours($detail);
        $kpiPerHour = (float) ($detail->kpi_per_hour ?: $detail->department?->kpi_per_hour ?? 0);
        $inventory  = (float) $detail->day_start_inventory;

        if ($kpiHours <= 0 || $kpiPerHour <= 0) {
            return 0;
        }

        return (int) ceil($inventory / $kpiPerHour / $kpiHours);
    }

    /**
     * Tổng số giờ nghỉ giải lao nằm trong khoảng [start_time, end_time) của ca.
     * Xử lý ca qua đêm (end_time < start_time).
     */
    private function computeBreakHoursWithinShift(ShiftDetail $detail): float
    {
        $shiftStart = $this->timeToMinutes($detail->start_time);
        $shiftEnd   = $this->timeToMinutes($detail->end_time);

        if ($shiftStart === null || $shiftEnd === null) {
            return 0;
        }

        // Ca qua đêm: end_time nhỏ hơn start_time → cộng thêm 1 ngày
        if ($shiftEnd <= $shiftStart) {
            $shiftEnd += 24 * 60;
        }

        $breaks = [
            ['start' => $detail->break1_start,     'minutes' => (int) $detail->break1_minutes],
            ['start' => $detail->meal_break_start,  'minutes' => (int) $detail->meal_break_minutes],
            ['start' => $detail->break2_start,      'minutes' => (int) $detail->break2_minutes],
            ['start' => $detail->break3_start,      'minutes' => (int) $detail->break3_minutes],
        ];

        $totalBreakMinutes = 0;

        foreach ($breaks as $break) {
            if (empty($break['start']) || $break['minutes'] <= 0) {
                continue;
            }

            $breakStart = $this->timeToMinutes($break['start']);
            if ($breakStart === null) {
                continue;
            }

            // Điều chỉnh ca qua đêm: nếu giờ nghỉ trước start_time thì thuộc ngày hôm sau
            if ($breakStart < $shiftStart) {
                $breakStart += 24 * 60;
            }

            // Chỉ tính nghỉ giải lao nằm trong ca [start_time, end_time)
            if ($breakStart >= $shiftStart && $breakStart < $shiftEnd) {
                $totalBreakMinutes += $break['minutes'];
            }
        }

        return $totalBreakMinutes / 60;
    }

    /**
     * Chuyển chuỗi giờ "HH:MM" hoặc "HH:MM:SS" sang số phút kể từ 00:00.
     */
    private function timeToMinutes(?string $time): ?int
    {
        if (empty($time)) {
            return null;
        }

        $parts = explode(':', $time);
        if (count($parts) < 2) {
            return null;
        }

        return (int) $parts[0] * 60 + (int) $parts[1];
    }
}
