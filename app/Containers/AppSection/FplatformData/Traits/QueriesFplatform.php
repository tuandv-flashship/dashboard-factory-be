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
     * Execute a SELECT query that returns multiple rows.
     *
     * Used by hourly metrics queries (productivity, staff count, etc.).
     * Returns empty array on failure instead of null.
     */
    protected function queryFplatformAll(string $sql, array $bindings): array
    {
        try {
            return DB::connection('fplatform')->select($sql, $bindings);
        } catch (\Illuminate\Database\QueryException $e) {
            Log::warning('[FplatformData] Query failed', [
                'error'    => $e->getMessage(),
                'bindings' => $bindings,
            ]);

            return [];
        }
    }

    /**
     * Format a standard inventory result (estimate_date, tong_viec).
     */
    protected function formatResult(?object $result, string $dateField = 'estimate_date'): ?array
    {
        if (!$result) {
            return null;
        }

        return [
            'estimate_date' => $result->{$dateField},
            'tong_viec'     => (int) $result->tong_viec,
        ];
    }

    /**
     * Format an order inventory result (estimate_date, tong_viec, da_lam).
     *
     * Used by order count queries that track both total work and completed work.
     */
    protected function formatOrderResult(?object $result, string $dateField = 'estimate_date'): ?array
    {
        if (!$result) {
            return null;
        }

        return [
            'estimate_date' => $result->{$dateField},
            'tong_viec'     => (int) $result->tong_viec,
            'da_lam'        => (int) $result->da_lam,
        ];
    }

    /**
     * Format a hotshot result (estimate_date, tong_viec, da_lam).
     */
    protected function formatHotshotResult(?object $result): ?array
    {
        if (!$result) {
            return null;
        }

        return [
            'estimate_date' => $result->estimate_date,
            'tong_viec'     => (int) $result->tong_viec,
            'da_lam'        => (int) $result->da_lam,
        ];
    }

    /**
     * Comma-separated quoted hotshot/reprint printer names for SQL IN clause.
     *
     * Used by Mockup, PackShip, Order, and Hotshot tasks to detect
     * HOTSHOT/REPRINT printers for date-cutoff logic.
     */
    protected function hotshotPrinterList(FactoryLine $factory): string
    {
        return match ($factory) {
            FactoryLine::FLS => "'MayHOTSHOT', 'MayREPRINT'",
            FactoryLine::PD  => "'MayHOTSHOTPD', 'MayREPRINTPD'",
        };
    }

    /**
     * Format hourly results into a standardized array.
     *
     * Converts stdClass rows into clean arrays with proper types.
     */
    protected function formatHourlyResults(array $rows, array $intFields = []): array
    {
        return array_map(function (object $row) use ($intFields) {
            $item = (array) $row;
            foreach ($intFields as $field) {
                if (isset($item[$field])) {
                    $item[$field] = (int) $item[$field];
                }
            }

            return $item;
        }, $rows);
    }
}
