<?php

namespace App\Containers\AppSection\FplatformData\Traits;

use App\Containers\AppSection\FplatformData\Enums\FactoryLine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Shared helpers for Tasks that query the fplatform database.
 *
 * Provides:
 * - Printer CTE/subquery building (DRY across 4+ tasks)
 * - Safe query execution with error handling for remote DB
 * - Standardized result formatting
 */
trait QueriesFplatform
{
    /**
     * Build the UNION ALL clauses for extra printers (MayHOTSHOT, MayREPRINT, etc.)
     */
    protected function buildExtraPrinterUnions(FactoryLine $factory): string
    {
        return collect($factory->extraPrinters())
            ->map(fn () => 'UNION ALL SELECT ?')
            ->implode(' ');
    }

    /**
     * Build bindings that include the factory value + extra printer names.
     */
    protected function printerBindings(FactoryLine $factory): array
    {
        return array_merge([$factory->value], $factory->extraPrinters());
    }

    /**
     * Execute a SELECT query on the fplatform connection with error handling.
     *
     * Returns null on connection failure or empty result,
     * logs warnings for remote DB issues instead of crashing.
     */
    protected function queryFplatform(string $sql, array $bindings): ?object
    {
        try {
            return DB::connection('fplatform')->selectOne($sql, $bindings);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::warning('[FplatformData] Query failed', [
                'error'    => $e->getMessage(),
                'bindings' => $bindings,
            ]);

            return null;
        }
    }

    /**
     * Format a standard inventory result (estimate_date, ton_dau, ton_cuoi).
     */
    protected function formatResult(?object $result, string $dateField = 'estimate_date'): ?array
    {
        if (!$result) {
            return null;
        }

        return [
            'estimate_date' => $result->{$dateField},
            'ton_dau'       => (int) $result->ton_dau,
            'ton_cuoi'      => (int) $result->ton_cuoi,
        ];
    }
}
