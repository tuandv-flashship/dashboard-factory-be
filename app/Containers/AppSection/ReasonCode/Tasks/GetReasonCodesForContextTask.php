<?php

namespace App\Containers\AppSection\ReasonCode\Tasks;

use App\Containers\AppSection\ReasonCode\Models\ReasonCategory;
use App\Ship\Parents\Tasks\Task as ParentTask;
use Illuminate\Database\Eloquent\Collection;

final class GetReasonCodesForContextTask extends ParentTask
{
    /**
     * Get all reason categories with sub-items (and their nested errors),
     * filtered by the given context and optional extra filters.
     *
     * Filters:
     *  - line          : scope_line match on sub_items
     *  - dept          : scope_dept match on sub_items
     *  - scope_type    : restrict sub_items to a specific scope_type
     *  - is_active     : override is_active filter on categories (default: true only)
     *  - search        : partial match on category code OR label (case-insensitive)
     *  - category_code : exact match on a single category code
     *
     * Hierarchy: category → subItems (filtered) → errors
     *
     * @return Collection<int, ReasonCategory>
     */
    public function run(
        ?string $line         = null,
        ?string $dept         = null,
        ?string $scopeType    = null,
        ?bool   $isActive     = true,
        ?string $search       = null,
        ?string $categoryCode = null,
    ): Collection {
        return ReasonCategory::query()
            // ── Category-level filters ──────────────────────────────────
            ->when($isActive !== null, fn($q) => $q->where('is_active', $isActive))
            ->when($search, fn($q) => $q->where(function ($q2) use ($search) {
                $q2->where('code', 'like', "%{$search}%")
                   ->orWhere('label', 'like', "%{$search}%")
                   ->orWhere('label_en', 'like', "%{$search}%");
            }))
            ->when($categoryCode, fn($q) => $q->where('code', $categoryCode))
            ->orderBy('sort_order')
            // ── Eager-load sub_items with context + scope_type filters ──
            ->with([
                'subItems' => function ($query) use ($line, $dept, $scopeType) {
                    $query->forContext($line, $dept)
                          ->when($scopeType, fn($q) => $q->where('scope_type', $scopeType))
                          ->with([
                              'errors' => fn($q) => $q->where('is_active', true)->orderBy('sort_order'),
                          ]);
                },
            ])
            ->get();
    }
}
