<?php

namespace App\Containers\AppSection\Machine\Models;

use App\Containers\AppSection\Department\Models\Department;
use App\Containers\AppSection\Machine\Enums\MachineStatus;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Machine extends ParentModel
{
    protected $table = 'machines';

    protected $fillable = [
        'department_id',
        'code',
        'name',
        'status',
        'description',
        'unit',
        'kpi_per_hour',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order'   => 'integer',
        'is_active'    => 'boolean',
        'kpi_per_hour' => 'integer',
        'status'       => MachineStatus::class,
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    /**
     * Scope: filter machines by status.
     */
    public function scopeByStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    /**
     * Scope: only active machines.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
