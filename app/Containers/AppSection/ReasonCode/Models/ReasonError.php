<?php

namespace App\Containers\AppSection\ReasonCode\Models;

use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReasonError extends ParentModel
{
    protected $table = 'reason_errors';

    protected $fillable = [
        'category_id',
        'sub_item_id',
        'code',
        'label',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────

    /**
     * Denormalized FK — direct access to category without JOIN through sub_item.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ReasonCategory::class, 'category_id');
    }

    /**
     * Primary FK — level 2 parent in the 3-level hierarchy.
     */
    public function subItem(): BelongsTo
    {
        return $this->belongsTo(ReasonSubItem::class, 'sub_item_id');
    }
}
