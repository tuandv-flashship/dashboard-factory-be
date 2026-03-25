<?php

namespace App\Containers\AppSection\Shift\UI\API\Transformers;

use App\Containers\AppSection\Shift\Models\Shift;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;
use League\Fractal\Resource\Collection;

final class ShiftTransformer extends ParentTransformer
{
    protected array $defaultIncludes = ['details'];
    protected array $availableIncludes = ['hourlyRecords'];

    public function transform(Shift $shift): array
    {
        $template = $shift->relationLoaded('template') ? $shift->template : null;

        return [
            'id'              => $shift->getHashedKey(),
            'date'            => $shift->date->toDateString(),
            'shift_number'    => $shift->shift_number,
            'start_time'      => $shift->start_time ? substr($shift->start_time, 0, 5) : null,
            'end_time'        => $shift->end_time ? substr($shift->end_time, 0, 5) : null,
            'supervisor'      => $shift->supervisor,
            'is_active'       => $shift->is_active,
            'template_id'     => $template?->getHashedKey(),
            'template_name'   => $template?->name,
            'template_color'  => $template?->color,
        ];
    }

    public function includeDetails(Shift $shift): Collection
    {
        return $this->collection($shift->details, new ShiftDetailTransformer(), 'shift_details');
    }

    public function includeHourlyRecords(Shift $shift): Collection
    {
        return $this->collection($shift->hourlyRecords, new HourlyRecordTransformer(), 'hourly_records');
    }
}
