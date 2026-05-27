<?php

namespace App\Containers\AppSection\Shift\Jobs;

use App\Containers\AppSection\Shift\Actions\CreateShiftAction;
use App\Containers\AppSection\Shift\Actions\UpdateShiftAction;
use App\Containers\AppSection\Shift\Models\Shift;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Create or override a shift for a single date.
 *
 * Dispatched in parallel via Bus::batch() from CreateShiftAction::runMultiDate().
 * Each job handles its own DB::transaction internally (via CreateShiftAction / UpdateShiftAction).
 *
 * - If shift (date + shift_number) does NOT exist → delegates to CreateShiftAction (create).
 * - If shift (date + shift_number) already exists → delegates to UpdateShiftAction (override).
 */
final class CreateShiftForDateJob implements ShouldQueue
{
    use Batchable;
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    /**
     * @param string $date      The target date (Y-m-d)
     * @param array  $shiftData Shared payload: shift_template_id, shift_numbers, supervisor, details
     */
    public function __construct(
        private readonly string $date,
        private readonly array  $shiftData,
    ) {
    }

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $shiftNumbers = $this->shiftData['shift_numbers'] ?: [1];

        foreach ($shiftNumbers as $shiftNumber) {
            $existing = Shift::where('date', $this->date)
                ->where('shift_number', $shiftNumber)
                ->first();

            if ($existing) {
                // ── Override existing shift ──
                $updateData = [
                    'supervisor' => $this->shiftData['supervisor'] ?? $existing->supervisor,
                ];

                if (!empty($this->shiftData['details'])) {
                    // Filter details to only include those matching this shift_number
                    $updateData['details'] = collect($this->shiftData['details'])
                        ->where('shift_number', $shiftNumber)
                        ->values()
                        ->toArray();
                }

                app(UpdateShiftAction::class)->run($existing->id, $updateData);

                Log::info('[CreateShiftForDate] Overridden existing shift.', [
                    'date'         => $this->date,
                    'shift_number' => $shiftNumber,
                    'shift_id'     => $existing->id,
                ]);
            } else {
                // ── Create new shift ──
                app(CreateShiftAction::class)->run(array_merge(
                    $this->shiftData,
                    ['date' => $this->date]
                ));

                Log::info('[CreateShiftForDate] Created new shift.', [
                    'date'         => $this->date,
                    'shift_number' => $shiftNumber,
                ]);
            }
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[CreateShiftForDate] Job failed', [
            'date'  => $this->date,
            'error' => $e->getMessage(),
        ]);
    }
}
