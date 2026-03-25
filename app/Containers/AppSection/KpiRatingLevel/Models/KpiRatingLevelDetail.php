<?php

namespace App\Containers\AppSection\KpiRatingLevel\Models;

use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class KpiRatingLevelDetail extends ParentModel
{
    protected $table = 'kpi_rating_level_details';

    protected $fillable = [
        'rating_level_id',
        'level_name',
        'bg_color',
        'text_color',
        'min_score',
        'operator',
        'requires_reason',
        'sort_order',
    ];

    protected $casts = [
        'min_score'       => 'decimal:2',
        'requires_reason' => 'boolean',
        'sort_order'      => 'integer',
    ];

    public function ratingLevel(): BelongsTo
    {
        return $this->belongsTo(KpiRatingLevel::class, 'rating_level_id');
    }
}
