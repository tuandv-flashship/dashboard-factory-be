<?php

namespace App\Containers\AppSection\ReasonCode\Models;

use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ReasonCategory extends ParentModel
{
    protected $table = 'reason_categories';

    protected $fillable = [
        'code',
        'label',
        'label_en',
        'icon',
        'color',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    public function subItems(): HasMany
    {
        return $this->hasMany(ReasonSubItem::class, 'category_id')
            ->orderBy('sort_order');
    }

    public function errors(): HasMany
    {
        return $this->hasMany(ReasonError::class, 'category_id')
            ->orderBy('sort_order');
    }
}
