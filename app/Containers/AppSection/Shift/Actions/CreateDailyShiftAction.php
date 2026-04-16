<?php

namespace App\Containers\AppSection\Shift\Actions;

use App\Containers\AppSection\Department\Enums\ProductivityType;
use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Machine\Models\Machine;
use App\Containers\AppSection\Shift\Enums\ShiftTemplateStatus;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Containers\AppSection\Shift\Models\ShiftDetail;
use App\Containers\AppSection\Shift\Models\ShiftTemplate;
use App\Containers\AppSection\Shift\Models\ShiftTemplateDetail;
use App\Containers\AppSection\Shift\Tasks\CreateShiftFromTemplateTask;
use App\Containers\AppSection\Shift\Tasks\FetchDailyInventoryForShiftTask;
use App\Containers\AppSection\Shift\Tasks\GenerateHourlyRecordsTask;
use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Auto-create shift 1 for a given date from the default template (Ca 1).
 *
 * Shared by: CreateDailyShiftJob, CreateDailyShiftCommand, CreateDailyShiftController.
 *
 * Flow:
 *   1. Check if shift already exists
 *      → YES: fetch tổng việc and update existing shift details
 *      → NO:  create shift from template
 *   2. Find first active template with applies_to_shift_1 = true
 *   3. Fetch tổng việc from Fplatform
 *   4. Auto-select all active machines for per_machine departments
 *   5. Create shift + details + hourly_records in transaction
 *
 * @return array{status: string, message: string, shift?: Shift}
 */
final class CreateDailyShiftAction extends ParentAction
{
    /**
     * @param  string|null $date Target date (Y-m-d). Defaults to today.
     * @return array{status: string, message: string, shift?: \App\Containers\AppSection\Shift\Models\Shift}
     */
    public function run(?string $date = null): array
    {
        $targetDate = $date ?? Carbon::today()->toDateString();

        // ── 1. Check existing shift ─────────────────────────
        $existingShift = Shift::whereDate('date', $targetDate)
            ->where('shift_number', 1)
            ->first();

        if ($existingShift) {
            return $this->updateInventoryForExistingShift($existingShift, $targetDate);
        }

        // ── 2. Find default template (Ca 1) ─────────────────
        $template = ShiftTemplate::where('status', ShiftTemplateStatus::ACTIVE)
            ->where('applies_to_shift_1', true)
            ->orderBy('sort_order')
            ->first();

        if (!$template) {
            return [
                'status'  => 'no_template',
                'message' => 'No active template with applies_to_shift_1 found.',
            ];
        }

        // ── 3. Load template details for shift_number = 1 ───
        $templateDetails = ShiftTemplateDetail::with('department')
            ->where('shift_template_id', $template->id)
            ->where('shift_number', 1)
            ->get();

        if ($templateDetails->isEmpty()) {
            return [
                'status'  => 'no_template',
                'message' => "Template '{$template->name}' has no details for shift_number=1.",
            ];
        }

        // ── 4. Fetch tổng việc from Fplatform ───────────
        $departments = Department::with('productionLine')
            ->whereIn('id', $templateDetails->pluck('department_id')->unique())
            ->get();

        $inventoryMap = $this->fetchInventory($targetDate, $departments);

        // ── 5. Build overrides (inventory + machines) ───────
        $overrides = [];
        foreach ($templateDetails as $td) {
            $override = [
                'department_id'       => $td->department_id,
                'shift_number'        => 1,
                'day_start_inventory' => $inventoryMap[$td->department_id] ?? 0,
            ];

            // Per-machine: auto-select all active machines in this department
            $isPerMachine = $td->department?->productivity_type === ProductivityType::PerMachine;
            if ($isPerMachine) {
                $override['machine_ids'] = Machine::active()
                    ->where('department_id', $td->department_id)
                    ->pluck('id')
                    ->toArray();
            }

            $overrides[] = $override;
        }

        // ── 6. Compute start/end from template details ──────
        $startTimes = $templateDetails->pluck('start_time')->filter();
        $minStart = $startTimes->min() ?? '06:00:00';

        $maxEnd = $templateDetails->map(function ($td) {
            $startRaw    = $td->start_time;
            $workHours   = $td->work_hours;
            $mealMinutes = $td->meal_break_minutes ?? 0;

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

        // ── 7. Create shift in transaction (re-check inside for race safety) ──
        try {
            $shift = DB::transaction(function () use ($targetDate, $template, $startFormatted, $maxEnd, $overrides) {
                // Re-check inside transaction to prevent race condition
                $existing = Shift::whereDate('date', $targetDate)
                    ->where('shift_number', 1)
                    ->lockForUpdate()
                    ->first();

                if ($existing) {
                    return $existing; // Another process already created it — will update inventory below
                }

                // Deactivate all previous shifts (keeps data, clears is_active flag)
                Shift::where('date', '<', $targetDate)
                    ->where('is_active', true)
                    ->update(['is_active' => false]);

                $shift = Shift::create([
                    'date'              => $targetDate,
                    'shift_number'      => 1,
                    'start_time'        => $startFormatted,
                    'end_time'          => $maxEnd,
                    'supervisor'        => null,
                    'is_active'         => true,
                    'shift_template_id' => $template->id,
                ]);

                // Copy template details → shift_details (with inventory + machines)
                app(CreateShiftFromTemplateTask::class)->run($shift, $template->id, $overrides);

                // Generate hourly_records skeleton
                app(GenerateHourlyRecordsTask::class)->run($shift);

                return $shift;
            });
        } catch (UniqueConstraintViolationException) {
            // Safety net: concurrent insert beat us — find and update inventory
            $existing = Shift::whereDate('date', $targetDate)->where('shift_number', 1)->first();
            if ($existing) {
                return $this->updateInventoryForExistingShift($existing, $targetDate);
            }

            return [
                'status'  => 'skipped',
                'message' => "Shift 1 already exists for {$targetDate} (concurrent create), skipping.",
            ];
        }

        // Race condition: another process created it during our setup — update inventory
        if ($shift->wasRecentlyCreated === false) {
            return $this->updateInventoryForExistingShift($shift, $targetDate);
        }

        $shift->load([
            'details.department.productionLine',
            'details.machines.machine',
            'template',
            'hourlyRecords',
        ]);

        return [
            'status'  => 'created',
            'message' => "Shift 1 created for {$targetDate} from template '{$template->name}'.",
            'shift'   => $shift,
        ];
    }

    /**
     * Fetch tổng việc from Fplatform and update day_start_inventory
     * on existing shift details.
     *
     * @return array{status: string, message: string, shift: Shift}
     */
    private function updateInventoryForExistingShift(Shift $shift, string $targetDate): array
    {
        $shiftDetails = $shift->details()->with('department.productionLine')->get();

        if ($shiftDetails->isEmpty()) {
            return [
                'status'  => 'skipped',
                'message' => "Shift 1 exists for {$targetDate} but has no details.",
                'shift'   => $shift,
            ];
        }

        $departments = $shiftDetails
            ->pluck('department')
            ->filter()
            ->unique('id')
            ->values();

        $inventoryMap = $this->fetchInventory($targetDate, $departments);

        // Nothing fetched — skip update
        if (empty($inventoryMap)) {
            return [
                'status'  => 'skipped',
                'message' => "Shift 1 exists for {$targetDate}. Inventory fetch returned empty, no update.",
                'shift'   => $shift,
            ];
        }

        // Bulk update each shift detail's day_start_inventory
        $updated = 0;
        foreach ($shiftDetails as $detail) {
            $inventory = $inventoryMap[$detail->department_id] ?? null;
            if ($inventory !== null && $detail->day_start_inventory !== $inventory) {
                $detail->update(['day_start_inventory' => $inventory]);
                $updated++;
            }
        }

        Log::info("[CreateDailyShift] Updated inventory for existing shift", [
            'date'    => $targetDate,
            'updated' => $updated,
            'map'     => $inventoryMap,
        ]);

        $shift->load([
            'details.department.productionLine',
            'details.machines.machine',
            'template',
        ]);

        return [
            'status'  => 'inventory_updated',
            'message' => "Shift 1 exists for {$targetDate}. Updated day_start_inventory for {$updated} department(s).",
            'shift'   => $shift,
        ];
    }

    /**
     * Fetch inventory from Fplatform with graceful fallback.
     *
     * @param  string                                                $date
     * @param  \Illuminate\Support\Collection<Department>|Collection $departments
     * @return array<int, int> department_id → tong_viec
     */
    private function fetchInventory(string $date, $departments): array
    {
        try {
            return app(FetchDailyInventoryForShiftTask::class)->run($date, $departments);
        } catch (\Throwable $e) {
            Log::warning('[CreateDailyShift] Fplatform fetch failed, using empty map', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }
}

