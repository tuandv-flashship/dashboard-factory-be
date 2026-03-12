<?php

namespace App\Containers\AppSection\ReasonCode\Models;

use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReasonSubItem extends ParentModel
{
    protected $table = 'reason_sub_items';

    protected $fillable = [
        'category_id',
        'code',
        'label',
        'scope_type',
        'scope_line',
        'scope_dept',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ReasonCategory::class, 'category_id');
    }

    /**
     * Scope: filter sub-items applicable to a given line + dept context.
     */
    public function scopeForContext($query, ?string $line, ?string $dept)
    {
        return $query->where('is_active', true)
            ->where(function ($q) use ($line, $dept) {
                // Global items always included
                $q->where('scope_type', 'global')
                    // Per-department items matching dept
                    ->orWhere(function ($q2) use ($dept) {
                        $q2->where('scope_type', 'per_department')
                            ->where(function ($q3) use ($dept) {
                                $q3->whereNull('scope_dept')
                                    ->orWhere('scope_dept', $dept);
                            });
                    })
                    // Per-line-department items matching both line and dept
                    ->orWhere(function ($q2) use ($line, $dept) {
                        $q2->where('scope_type', 'per_line_department')
                            ->where(function ($q3) use ($line) {
                                $q3->whereNull('scope_line')
                                    ->orWhere('scope_line', $line);
                            })
                            ->where(function ($q3) use ($dept) {
                                $q3->whereNull('scope_dept')
                                    ->orWhere('scope_dept', $dept);
                            });
                    });
            })
            ->orderBy('sort_order');
    }
}
