<?php

namespace App\Containers\AppSection\Order\Tasks;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\Order\Models\OrderSummary;
use App\Containers\AppSection\Shift\Models\Shift;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class GetOrderSummaryTask extends ParentTask
{
    /**
     * Default line definitions per factory.
     *
     * FLS: DTF only
     * PD:  DTF + DTG
     */
    private const LINE_DEFAULTS = [
        'FLS' => [
            ['line' => 'dtf', 'line_label' => 'DTF'],
        ],
        'PD' => [
            ['line' => 'dtf', 'line_label' => 'DTF'],
            ['line' => 'dtg', 'line_label' => 'DTG'],
        ],
    ];

    /**
     * Get order summaries (total + per-line).
     *
     * Resolution logic:
     *   - date + shift → exact match
     *   - date only   → latest shift_number for that date
     *   - neither     → today + latest shift
     *
     * When no synced data exists yet, returns default empty rows
     * for each production line so the FE always has a consistent structure.
     */
    public function run(?string $date = null, ?int $shiftNumber = null): array
    {
        $date = $date ?? now()->toDateString();

        // Resolve shift number: use provided, or find latest for this date
        if ($shiftNumber === null) {
            $shift = Shift::query()
                ->where('date', $date)
                ->latest('shift_number')
                ->first();

            $shiftNumber = $shift?->shift_number ?? 1;
        }

        $perLine = OrderSummary::query()
            ->forShift($date, $shiftNumber)
            ->perLine()
            ->get();

        // When no synced data exists yet, provide default empty rows
        if ($perLine->isEmpty()) {
            $perLine = $this->buildDefaultPerLine($date, $shiftNumber);
        }

        // Compute total from per-line data (no separate DB row needed)
        $total = $perLine->isNotEmpty() ? [
            'total'          => $perLine->sum('total'),
            'completed'      => $perLine->sum('completed'),
            'remaining'      => $perLine->sum('remaining'),
            'rush_completed' => $perLine->sum('rush_completed'),
            'rush_total'     => $perLine->sum('rush_total'),
            'progress'       => $perLine->sum('total') > 0
                ? round(($perLine->sum('completed') / $perLine->sum('total')) * 100, 1)
                : 0,
            'estimated_done' => $perLine->max('estimated_done') ?? '--',
        ] : null;

        return [
            'date'         => $date,
            'shift_number' => $shiftNumber,
            'total'        => $total,
            'per_line'     => $perLine,
        ];
    }

    /**
     * Build default empty OrderSummary instances (unsaved) for each line.
     *
     * Uses FactoryLine::current() to determine which lines are applicable.
     * Returns a Collection of OrderSummary models with zeroed values.
     *
     * @return \Illuminate\Support\Collection<int, OrderSummary>
     */
    private function buildDefaultPerLine(string $date, int $shiftNumber): \Illuminate\Support\Collection
    {
        $factory = FactoryLine::current();
        $lines = self::LINE_DEFAULTS[$factory->value] ?? self::LINE_DEFAULTS['PD'];

        return collect($lines)->map(fn (array $def) => new OrderSummary([
            'date'                    => $date,
            'shift_number'            => $shiftNumber,
            'line'                    => $def['line'],
            'line_label'              => $def['line_label'],
            'total'                   => 0,
            'completed'               => 0,
            'remaining'               => 0,
            'estimated_done'          => '--',
            'rush_completed'          => 0,
            'rush_total'              => 0,
            'progress'                => 0,
            'production_workload_json' => null,
        ]));
    }
}
