<?php

namespace App\Containers\AppSection\KpiRatingLevel\Models;

use App\Containers\AppSection\KpiRatingLevel\Enums\KpiRatingLevelStatus;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

final class KpiRatingLevel extends ParentModel
{
    protected $table = 'kpi_rating_levels';

    protected $fillable = [
        'name',
        'effective_from',
        'effective_until',
        'description',
    ];

    protected $casts = [
        'effective_from'  => 'date',
        'effective_until' => 'date',
    ];

    // ── Relationships ────────────────────────────────────

    public function details(): HasMany
    {
        return $this->hasMany(KpiRatingLevelDetail::class, 'rating_level_id')
            ->orderBy('sort_order');
    }

    // ── Accessors ────────────────────────────────────────

    protected function status(): Attribute
    {
        return Attribute::get(function (): KpiRatingLevelStatus {
            $today = Carbon::today();

            if ($this->effective_from > $today) {
                return KpiRatingLevelStatus::PENDING;
            }

            if ($this->effective_until !== null && $this->effective_until < $today) {
                return KpiRatingLevelStatus::EXPIRED;
            }

            return KpiRatingLevelStatus::ACTIVE;
        });
    }
}
