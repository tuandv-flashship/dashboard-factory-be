<?php

namespace App\Containers\AppSection\Shift\Models;

use App\Containers\AppSection\Shift\Enums\ShiftTemplateStatus;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ShiftTemplate extends ParentModel
{
    protected $table = 'shift_templates';

    protected $fillable = [
        'name',
        'color',
        'description',
        'sort_order',
        'status',
        'applies_to_shift_1',
        'applies_to_shift_2',
    ];

    protected $casts = [
        'sort_order'          => 'integer',
        'status'              => ShiftTemplateStatus::class,
        'applies_to_shift_1'  => 'boolean',
        'applies_to_shift_2'  => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────

    public function details(): HasMany
    {
        return $this->hasMany(ShiftTemplateDetail::class, 'shift_template_id')
            ->orderBy('department_id')
            ->orderBy('shift_number');
    }
}
