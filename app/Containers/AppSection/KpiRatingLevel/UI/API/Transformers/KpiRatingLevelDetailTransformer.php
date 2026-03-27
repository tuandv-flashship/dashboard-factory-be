<?php

namespace App\Containers\AppSection\KpiRatingLevel\UI\API\Transformers;

use App\Containers\AppSection\KpiRatingLevel\Models\KpiRatingLevelDetail;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class KpiRatingLevelDetailTransformer extends ParentTransformer
{
    public function transform(KpiRatingLevelDetail $detail): array
    {
        return [
            'id'              => $detail->getHashedKey(),
            'level_name'      => $detail->level_name,
            'bg_color'        => $detail->bg_color,
            'text_color'      => $detail->text_color,
            'min_score'       => (float) $detail->min_score,
            'operator'        => $detail->operator,
            'is_kpi_threshold'          => $detail->is_kpi_threshold,
            'is_staff_warning_threshold' => $detail->is_staff_warning_threshold,
            'sort_order'      => $detail->sort_order,
        ];
    }
}
