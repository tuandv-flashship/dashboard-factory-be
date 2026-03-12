<?php

namespace App\Containers\AppSection\Machine\Models;

use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Builder;

final class Machine extends ParentModel
{
    protected $table = 'machines';

    protected $fillable = [
        'code',
        'name',
        'status',
        'department',
        'line',
        'description',
        'sort_order',
        'is_active',
    ];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Scope: filter machines by production line.
     */
    public function scopeForLine(Builder $query, string $line): Builder
    {
        return $query->where('line', $line);
    }

    /**
     * Scope: filter machines by department.
     */
    public function scopeForDepartment(Builder $query, string $department): Builder
    {
        return $query->where('department', $department);
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
