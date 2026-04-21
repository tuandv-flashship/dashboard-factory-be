<?php

namespace App\Containers\AppSection\ReasonCode\Models;

use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'is_active'  => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    public function category(): BelongsTo
    {
        return $this->belongsTo(ReasonCategory::class, 'category_id');
    }

    /**
     * Level 3 — specific errors belonging to this sub-item.
     */
    public function errors(): HasMany
    {
        return $this->hasMany(ReasonError::class, 'sub_item_id')->orderBy('sort_order');
    }

    // ── Scopes ─────────────────────────────────────────────────────────

    /**
     * Filter sub-items applicable to a given line + department context.
     */
    public function scopeForContext($query, ?string $line, ?string $dept)
    {
        return $query->where('is_active', true)
            ->where(function ($q) use ($line, $dept) {
                $q->where('scope_type', 'global')
                    ->orWhere(function ($q2) use ($dept) {
                        $q2->where('scope_type', 'per_department')
                            ->where(fn($q3) => $q3->whereNull('scope_dept')->orWhere('scope_dept', $dept));
                    })
                    ->orWhere(function ($q2) use ($line, $dept) {
                        $q2->where('scope_type', 'per_line_department')
                            ->where(fn($q3) => $q3->whereNull('scope_line')->orWhere('scope_line', $line))
                            ->where(fn($q3) => $q3->whereNull('scope_dept')->orWhere('scope_dept', $dept));
                    });
            })
            ->orderBy('sort_order');
    }
}
