<?php

namespace App\Containers\AppSection\Alert\Models;

use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Builder;

final class Alert extends ParentModel
{
    protected $table = 'alerts';

    protected $fillable = [
        'severity', 'department', 'time', 'message', 'line',
        'is_resolved', 'resolved_at',
    ];

    protected $casts = [
        'is_resolved' => 'boolean',
        'resolved_at' => 'immutable_datetime',
    ];

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->where('is_resolved', false);
    }

    public function scopeForLine(Builder $query, string $line): Builder
    {
        return $query->where(function ($q) use ($line) {
            $q->where('line', $line)->orWhere('line', 'all');
        });
    }

    public function scopeBySeverity(Builder $query, string $severity): Builder
    {
        return $query->where('severity', $severity);
    }
}
