<?php

namespace App\Containers\AppSection\Production\Models;

use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HourlyIssue extends ParentModel
{
    protected $table = 'hourly_issues';

    protected $fillable = [
        'hourly_record_id', 'category', 'sub_item', 'error', 'note', 'resolved_at', 'resolution',
    ];

    protected $casts = [
        'resolved_at' => 'immutable_datetime',
    ];

    public function hourlyRecord(): BelongsTo
    {
        return $this->belongsTo(HourlyRecord::class, 'hourly_record_id');
    }
}
