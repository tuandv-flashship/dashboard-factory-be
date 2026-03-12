<?php

namespace App\Containers\AppSection\ReasonCode\Models;

use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReasonError extends ParentModel
{
    protected $table = 'reason_errors';

    protected $fillable = [
        'category_id',
        'code',
        'label',
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
     * Scope: filter errors applicable to a given department context.
     */
    public function scopeForDept($query, ?string $dept)
    {
        return $query->where('is_active', true)
            ->where(function ($q) use ($dept) {
                $q->whereNull('scope_dept')        // common errors (all depts)
                    ->orWhere('scope_dept', $dept); // dept-specific errors
            })
            ->orderBy('sort_order');
    }
}
