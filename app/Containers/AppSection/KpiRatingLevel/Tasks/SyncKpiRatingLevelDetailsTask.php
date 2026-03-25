<?php

namespace App\Containers\AppSection\KpiRatingLevel\Tasks;

use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevelDetail;
use App\Ship\Parents\Tasks\Task as ParentTask;

final class SyncKpiRatingLevelDetailsTask extends ParentTask
{
    /**
     * Delete existing details and recreate from the given array.
     *
     * @param  int   $ratingLevelId
     * @param  array $details  Array of detail rows
     */
    public function run(int $ratingLevelId, array $details): void
    {
        // Remove all existing details
        KpiRatingLevelDetail::where('rating_level_id', $ratingLevelId)->delete();

        // Recreate
        foreach ($details as $index => $detail) {
            KpiRatingLevelDetail::create([
                'rating_level_id' => $ratingLevelId,
                'level_name'      => $detail['level_name'],
                'bg_color'        => $detail['bg_color'],
                'text_color'      => $detail['text_color'],
                'min_score'       => $detail['min_score'],
                'operator'        => $detail['operator'] ?? '>=',
                'requires_reason' => $detail['requires_reason'] ?? false,
                'sort_order'      => $detail['sort_order'] ?? $index + 1,
            ]);
        }
    }
}
