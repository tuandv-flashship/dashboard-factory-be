<?php

namespace App\Containers\AppSection\Quality\UI\API\Transformers;

use App\Containers\AppSection\Quality\Models\QualityRecord;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class QualityRecordTransformer extends ParentTransformer
{
    public function transform(QualityRecord $record): array
    {
        return [
            'id' => $record->getHashedKey(),
            'pass_rate' => $record->pass_rate,
            'inspected' => $record->inspected,
            'passed' => $record->passed,
            'failed' => $record->failed,
            'avg_error_rate' => $record->avg_error_rate,
        ];
    }
}
