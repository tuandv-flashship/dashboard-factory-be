<?php

namespace App\Containers\AppSection\FplatformData\Actions;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use App\Containers\AppSection\FplatformData\Tasks\GetOrderByEstimateTask;
use App\Ship\Parents\Actions\Action as ParentAction;

/**
 * Orchestrate order-by-estimate data across DTF + DTG lines.
 *
 * PD factory: merges DTF + DTG rows by estimate_date.
 * FLS factory: DTF only.
 */
final class GetOrderByEstimateAction extends ParentAction
{
    public function __construct(
        private readonly GetOrderByEstimateTask $task,
    ) {
    }

    /**
     * @return array{data: array, lines: array}
     */
    public function run(string $date): array
    {
        $factory = FactoryLine::current();

        $dtfRows = $this->task->runDtf($date, $factory);
        $dtgRows = $factory === FactoryLine::PD
            ? $this->task->runDtg($date)
            : [];

        // Index DTG rows by estimate_date for merging
        $dtgByDate = collect($dtgRows)->keyBy('estimate_date');

        $merged = [];
        $allDates = collect($dtfRows)->pluck('estimate_date')
            ->merge(collect($dtgRows)->pluck('estimate_date'))
            ->unique()
            ->sortDesc()
            ->values();

        $dtfByDate = collect($dtfRows)->keyBy('estimate_date');

        $zero = ['tong_don' => 0, 'da_lam' => 0, 'chua_lam' => 0];

        foreach ($allDates as $estimateDate) {
            $dtf = $dtfByDate->get($estimateDate, $zero);
            $dtg = $dtgByDate->get($estimateDate, $zero);

            $row = [
                'estimate_date' => $estimateDate,
                'tong_don'      => $dtf['tong_don'] + $dtg['tong_don'],
                'da_lam'        => $dtf['da_lam'] + $dtg['da_lam'],
                'chua_lam'      => $dtf['chua_lam'] + $dtg['chua_lam'],
                'lines'         => ['dtf' => $dtf],
            ];

            if ($factory === FactoryLine::PD) {
                $row['lines']['dtg'] = $dtg;
            }

            $merged[] = $row;
        }

        return $merged;
    }
}
