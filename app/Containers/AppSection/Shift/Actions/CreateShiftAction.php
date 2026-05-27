<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Shift\Jobs\CreateShiftForDateJob;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Containers\AppSection\Shift\Tasks\CreateShiftFromTemplateTask;
use App\Containers\AppSection\Shift\Tasks\GenerateHourlyRecordsTask;
use App\Containers\AppSection\Production\Support\ProductionCacheKeys;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class CreateShiftAction extends ParentAction
{
    /**
     * Create shift(s) for a single date (synchronous).
     * Kept unchanged for backward compatibility.
     */
    public function run(array $data): Shift
    {
        $shift = DB::transaction(function () use ($data) {
            $template     = ShiftTemplate::with('details')->findOrFail($data['shift_template_id']);
            $shiftNumbers = $data['shift_numbers'] ?: [1];

            // FE có thể gửi kèm details đã chỉnh sửa (những cell màu trắng trong mockup).
            $overrides = $data['details'] ?? [];

            $lastShift = null;

            foreach ($shiftNumbers as $shiftNumber) {
                // Lấy template details cho shift_number này
                $templateDetails = $template->details
                    ->where('shift_number', $shiftNumber);

                if ($templateDetails->isEmpty()) {
                    continue;
                }

                // Gộp override để tính start/end chính xác cho header shift
                $overrideMap = collect($overrides)
                    ->where('shift_number', $shiftNumber)
                    ->keyBy(fn ($o) => "{$o['department_id']}|{$o['shift_number']}");

                $startTimes = $templateDetails->map(function ($td) use ($overrideMap) {
                    $key       = "{$td->department_id}|{$td->shift_number}";
                    $override  = $overrideMap->get($key, []);

                    return $override['start_time'] ?? $td->start_time;
                })->filter();

                $minStart = $startTimes->min() ?? '06:00:00';

                // end_time = start_time + work_hours (net) + meal_break_minutes
                // Nhất quán với ShiftDetail::endTime accessor
                $maxEnd = $templateDetails->map(function ($td) use ($overrideMap) {
                    $key       = "{$td->department_id}|{$td->shift_number}";
                    $override  = $overrideMap->get($key, []);

                    $startRaw       = $override['start_time']         ?? $td->start_time;
                    $workHours      = $override['work_hours']         ?? $td->work_hours;
                    $mealMinutes    = $override['meal_break_minutes'] ?? ($td->meal_break_minutes ?? 0);

                    $totalMinutes = (int) ($workHours * 60) + (int) $mealMinutes;
                    $format       = substr_count($startRaw, ':') === 2 ? 'H:i:s' : 'H:i';

                    return Carbon::createFromFormat($format, $startRaw)
                        ->addMinutes($totalMinutes)
                        ->format('H:i');
                })->max() ?? '14:00';

                $startFormatted = Carbon::createFromFormat(
                    substr_count($minStart, ':') === 2 ? 'H:i:s' : 'H:i',
                    $minStart
                )->format('H:i');

                $createdShift = Shift::create([
                    'date'              => $data['date'],
                    'shift_number'      => $shiftNumber,
                    'start_time'        => $startFormatted,
                    'end_time'          => $maxEnd,
                    'supervisor'        => $data['supervisor'] ?? null,
                    'is_active'         => true,
                    'shift_template_id' => $template->id,
                ]);

                // Copy template details → shift_details, merge override nếu có
                app(CreateShiftFromTemplateTask::class)->run($createdShift, $template->id, $overrides);

                // Generate hourly_records
                app(GenerateHourlyRecordsTask::class)->run($createdShift);

                $lastShift = $createdShift;
            }

            return $lastShift->load(['details.department.productionLine', 'details.machines.machine', 'template', 'hourlyRecords']);
        });

        // ── Invalidate production dashboard caches (after transaction commits) ──
        ProductionCacheKeys::flushForShift($shift);

        return $shift;
    }

    /**
     * Dispatch parallel jobs for multi-date creation/override.
     *
     * Each date gets its own CreateShiftForDateJob running in its own transaction.
     * Jobs are dispatched via Bus::batch() for parallel execution by queue workers.
     *
     * @param  array  $dates     Array of date strings (Y-m-d)
     * @param  array  $shiftData Shared payload: shift_template_id, shift_numbers, supervisor, details
     * @return array{batch_id: string|null, total: int, past_ignored: string[]}
     */
    public function runMultiDate(array $dates, array $shiftData): array
    {
        $today = today()->toDateString();

        // Filter: only keep today + future dates
        $pastIgnored = array_values(array_filter($dates, fn ($d) => $d < $today));
        $validDates  = array_values(array_filter($dates, fn ($d) => $d >= $today));

        if (empty($validDates)) {
            return [
                'batch_id'     => null,
                'total'        => 0,
                'past_ignored' => $pastIgnored,
            ];
        }

        // Dispatch 1 job per date via Bus::batch() → parallel execution
        $jobs = array_map(
            fn (string $date) => new CreateShiftForDateJob($date, $shiftData),
            $validDates
        );

        $batchName = sprintf(
            'create-shifts:%s~%s(%d)',
            $validDates[0],
            end($validDates),
            count($validDates),
        );

        $batch = Bus::batch($jobs)
            ->name($batchName)
            ->onQueue('sync')
            ->allowFailures()
            ->dispatch();

        Log::info('[CreateShift] Multi-date batch dispatched.', [
            'batch_id'     => $batch->id,
            'dates'        => $validDates,
            'past_ignored' => $pastIgnored,
        ]);

        return [
            'batch_id'     => $batch->id,
            'total'        => count($validDates),
            'past_ignored' => $pastIgnored,
        ];
    }
}

