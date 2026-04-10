<?php

namespace App\Containers\AppSection\Production\Models;

use App\Containers\AppSection\Department\Models\Department;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ProductionLine extends ParentModel
{
    protected $table = 'production_lines';

    protected $fillable = [
        'code', 'label', 'color', 'subtitle', 'sort_order', 'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    /**
     * Cross-container relation: departments belong to Department container.
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class, 'production_line_id')->orderBy('sort_order');
    }
}
