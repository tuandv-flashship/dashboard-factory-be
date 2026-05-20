<?php

namespace App\Containers\AppSection\FplatformData\Services;

use App\Containers\AppSection\Shift\Models\ShiftDetail;
use Illuminate\Support\Carbon;

/**
 * Allocate CUT images (total_file) into hourly blocks proportionally
 * based on actual working time (minus break overlaps).
 *
 * Algorithm (7 steps from spec):
 *   1. Group log records by username, sort by created_at ASC
 *   2. Infer Start time: first file → shift start; subsequent → previous file's End time
 *   3. For each file, find overlapping 1h blocks
 *   4. A = working minutes in block (before break deduction)
 *   5. B = break minutes overlapping block AND file time
 *   6. C = A - B (actual minutes)
 *   7. Rate = C / ΣC → Images = Round(Rate × total_file, 0)
 *
 * @see docs/tinh-so-hinh-thuc-te-trong-block-gio.md
 */
final class CutHourlyImageAllocator
{
    /**
     * Allocate images aggregated across all users into hourly blocks.
     *
     * @param  array  $logs            [{username, created_at, total_file}, ...]
     * @param  string $shiftDate       "Y-m-d"
     * @param  string $shiftStartTime  "HH:mm:ss" — CUT department shift start
     * @param  array  $breaks          [{start: Carbon, end: Carbon}, ...]
     * @return array<string, int>      [hourKey => totalImages] e.g. ['2026-04-14 08' => 125]
     */
    public function allocate(array $logs, string $shiftDate, string $shiftStartTime, array $breaks): array
    {
        $filesWithTiming = $this->buildFilesWithTiming($logs, $shiftDate, $shiftStartTime);

        $hourMap = [];

        foreach ($filesWithTiming as $file) {
            $allocation = $this->allocateFile($file, $breaks);

            foreach ($allocation as $hourKey => $images) {
                $hourMap[$hourKey] = ($hourMap[$hourKey] ?? 0) + $images;
            }
        }

        ksort($hourMap);

        return $hourMap;
    }

    /**
     * Allocate images per-user into hourly blocks.
     *
     * @param  array  $logs            [{username, created_at, total_file}, ...]
     * @param  string $shiftDate       "Y-m-d"
     * @param  string $shiftStartTime  "HH:mm:ss" — CUT department shift start
     * @param  array  $breaks          [{start: Carbon, end: Carbon}, ...]
     * @return array<int, array{date_hour: string, username: string, value: int}>
     */
    public function allocatePerUser(array $logs, string $shiftDate, string $shiftStartTime, array $breaks): array
    {
        $filesWithTiming = $this->buildFilesWithTiming($logs, $shiftDate, $shiftStartTime);

        return $this->computePerUser($filesWithTiming, $breaks);
    }

    /**
     * Compute BOTH aggregate and per-user allocations in a single pass.
     *
     * Optimized for sync pipeline — avoids duplicate buildFilesWithTiming
     * and allocateFile calls when both maps are needed from the same data.
     *
     * @return array{0: array<string, int>, 1: array<int, array{date_hour: string, username: string, value: int}>}
     *         [aggregateMap, perUserItems]
     */
    public function allocateBoth(array $logs, string $shiftDate, string $shiftStartTime, array $breaks): array
    {
        $filesWithTiming = $this->buildFilesWithTiming($logs, $shiftDate, $shiftStartTime);

        // Single pass: compute allocation per file, aggregate both maps
        $hourMap = [];
        $userHourMap = [];

        foreach ($filesWithTiming as $file) {
            $allocation = $this->allocateFile($file, $breaks);
            $username = $file['username'];

            foreach ($allocation as $hourKey => $images) {
                $hourMap[$hourKey] = ($hourMap[$hourKey] ?? 0) + $images;
                $userHourMap[$username][$hourKey] = ($userHourMap[$username][$hourKey] ?? 0) + $images;
            }
        }

        ksort($hourMap);

        // Flatten per-user map
        $perUserItems = $this->flattenPerUserMap($userHourMap);

        return [$hourMap, $perUserItems];
    }

    /**
     * Extract break periods from a ShiftDetail into Carbon interval pairs.
     *
     * @return array<int, array{start: Carbon, end: Carbon}>
     */
    public static function extractBreaks(ShiftDetail $detail, string $shiftDate): array
    {
        $breaks = [];

        $breakFields = [
            ['break1_start', 'break1_minutes'],
            ['meal_break_start', 'meal_break_minutes'],
            ['break2_start', 'break2_minutes'],
            ['break3_start', 'break3_minutes'],
        ];

        foreach ($breakFields as [$startField, $minutesField]) {
            $startTime = $detail->{$startField};
            $minutes = (int) ($detail->{$minutesField} ?? 0);

            if ($startTime === null || $minutes <= 0) {
                continue;
            }

            $start = Carbon::createFromFormat('Y-m-d H:i:s', "{$shiftDate} {$startTime}");
            $breaks[] = [
                'start' => $start,
                'end'   => $start->copy()->addMinutes($minutes),
            ];
        }

        return $breaks;
    }

    // ── Internal methods ─────────────────────────────────

    /**
     * Compute per-user allocations from pre-built file timing data.
     */
    private function computePerUser(array $filesWithTiming, array $breaks): array
    {
        $userHourMap = [];

        foreach ($filesWithTiming as $file) {
            $allocation = $this->allocateFile($file, $breaks);
            $username = $file['username'];

            foreach ($allocation as $hourKey => $images) {
                $userHourMap[$username][$hourKey] = ($userHourMap[$username][$hourKey] ?? 0) + $images;
            }
        }

        return $this->flattenPerUserMap($userHourMap);
    }

    /**
     * Flatten [username => [hourKey => images]] into sorted [{date_hour, username, value}].
     */
    private function flattenPerUserMap(array $userHourMap): array
    {
        $result = [];
        foreach ($userHourMap as $username => $hours) {
            ksort($hours);
            foreach ($hours as $hourKey => $value) {
                $result[] = [
                    'date_hour' => $hourKey,
                    'username'  => $username,
                    'value'     => $value,
                ];
            }
        }

        usort($result, fn ($a, $b) => $a['date_hour'] <=> $b['date_hour'] ?: $a['username'] <=> $b['username']);

        return $result;
    }

    /**
     * Group logs by user, sort by created_at, infer start times.
     *
     * @return array<int, array{username: string, start: Carbon, end: Carbon, total_file: int}>
     */
    private function buildFilesWithTiming(array $logs, string $shiftDate, string $shiftStartTime): array
    {
        if (empty($logs)) {
            return [];
        }

        // Group by username
        $grouped = [];
        foreach ($logs as $log) {
            $grouped[$log['username']][] = $log;
        }

        $files = [];

        foreach ($grouped as $username => $userLogs) {
            // Sort by created_at ASC (End time)
            usort($userLogs, fn ($a, $b) => strcmp($a['created_at'], $b['created_at']));

            $shiftStart = Carbon::createFromFormat('Y-m-d H:i:s', "{$shiftDate} {$shiftStartTime}");

            foreach ($userLogs as $i => $log) {
                $endTime = Carbon::parse($log['created_at']);

                // Rule: first file → shift start; subsequent → previous file's end time
                $startTime = $i === 0
                    ? $shiftStart->copy()
                    : Carbon::parse($userLogs[$i - 1]['created_at']);

                // Guard: start must be before end
                if ($startTime->gte($endTime)) {
                    $startTime = $endTime->copy();
                }

                $files[] = [
                    'username'   => $username,
                    'start'      => $startTime,
                    'end'        => $endTime,
                    'total_file' => (int) $log['total_file'],
                ];
            }
        }

        return $files;
    }

    /**
     * Allocate a single file's images across hourly blocks.
     *
     * @param  array $file   {username, start: Carbon, end: Carbon, total_file: int}
     * @param  array $breaks [{start: Carbon, end: Carbon}, ...]
     * @return array<string, int> [hourKey => images]
     */
    private function allocateFile(array $file, array $breaks): array
    {
        $totalFile = $file['total_file'];

        if ($totalFile <= 0) {
            return [];
        }

        $fileStart = $file['start'];
        $fileEnd = $file['end'];

        // File has zero duration → assign all to the End time block
        if ($fileStart->gte($fileEnd)) {
            $hourKey = $fileEnd->format('Y-m-d H');

            return [$hourKey => $totalFile];
        }

        // Determine which hourly blocks this file spans
        $blockStartHour = $fileStart->copy()->startOfHour();
        $blockEndHour = $fileEnd->copy()->startOfHour();

        // If file ends exactly on the hour boundary, the last block is the previous hour
        if ($fileEnd->eq($blockEndHour) && $blockEndHour->gt($blockStartHour)) {
            $blockEndHour->subHour();
        }

        // Calculate C (actual minutes) for each block
        $blockMinutes = []; // [hourKey => C_minutes]
        $totalC = 0.0;

        $currentBlock = $blockStartHour->copy();
        while ($currentBlock->lte($blockEndHour)) {
            $blockStart = $currentBlock->copy();
            $blockEnd = $currentBlock->copy()->addHour();
            $hourKey = $currentBlock->format('Y-m-d H');

            // A = working minutes in block (before break deduction)
            $a = $this->calcWorkMinutes($fileStart, $fileEnd, $blockStart, $blockEnd);

            // B = total break minutes overlapping block AND file time
            $b = $this->calcBreakMinutes($fileStart, $fileEnd, $blockStart, $blockEnd, $breaks);

            // C = actual working minutes
            $c = max(0.0, $a - $b);

            if ($c > 0) {
                $blockMinutes[$hourKey] = $c;
                $totalC += $c;
            }

            $currentBlock->addHour();
        }

        // Edge case: ΣC = 0 → fallback: assign all to End time block
        if ($totalC <= 0 || empty($blockMinutes)) {
            $hourKey = $fileEnd->format('Y-m-d H');

            return [$hourKey => $totalFile];
        }

        // Calculate images per block: Rate = C / ΣC → Images = Round(Rate × total_file, 0)
        $allocation = [];
        $allocatedTotal = 0;
        $maxRateKey = null;
        $maxRateValue = 0.0;

        foreach ($blockMinutes as $hourKey => $c) {
            $rate = $c / $totalC;
            $images = (int) round($rate * $totalFile);
            $allocation[$hourKey] = $images;
            $allocatedTotal += $images;

            if ($rate > $maxRateValue) {
                $maxRateValue = $rate;
                $maxRateKey = $hourKey;
            }
        }

        // Rounding correction: adjust the block with highest rate
        $diff = $totalFile - $allocatedTotal;
        if ($diff !== 0 && $maxRateKey !== null) {
            $allocation[$maxRateKey] += $diff;
        }

        return $allocation;
    }

    /**
     * A = max(0, min(EndTime, BlockEnd) - max(StartTime, BlockStart))
     *
     * Calculate working minutes of a file within a specific block (before break deduction).
     */
    private function calcWorkMinutes(Carbon $fileStart, Carbon $fileEnd, Carbon $blockStart, Carbon $blockEnd): float
    {
        $overlapStart = $fileStart->gt($blockStart) ? $fileStart : $blockStart;
        $overlapEnd = $fileEnd->lt($blockEnd) ? $fileEnd : $blockEnd;

        $minutes = $overlapEnd->floatDiffInMinutes($overlapStart, false);

        return max(0.0, $minutes);
    }

    /**
     * B = Σ max(0, min(BreakEnd, BlockEnd, FileEnd) - max(BreakStart, FileStart, BlockStart))
     *
     * Calculate total break minutes overlapping with both the block AND the file's active time.
     * Uses 3-way max for start and 3-way min for end to ensure only breaks
     * during the file's processing window within the block are deducted.
     */
    private function calcBreakMinutes(Carbon $fileStart, Carbon $fileEnd, Carbon $blockStart, Carbon $blockEnd, array $breaks): float
    {
        $totalBreak = 0.0;

        foreach ($breaks as $break) {
            $breakStart = $break['start'];
            $breakEnd = $break['end'];

            // 3-way max: max(BreakStart, FileStart, BlockStart)
            $overlapStart = $breakStart;
            if ($fileStart->gt($overlapStart)) {
                $overlapStart = $fileStart;
            }
            if ($blockStart->gt($overlapStart)) {
                $overlapStart = $blockStart;
            }

            // 3-way min: min(BreakEnd, BlockEnd, FileEnd)
            $overlapEnd = $breakEnd;
            if ($blockEnd->lt($overlapEnd)) {
                $overlapEnd = $blockEnd;
            }
            if ($fileEnd->lt($overlapEnd)) {
                $overlapEnd = $fileEnd;
            }

            $minutes = $overlapEnd->floatDiffInMinutes($overlapStart, false);
            $totalBreak += max(0.0, $minutes);
        }

        return $totalBreak;
    }
}
