<?php

namespace App\Containers\AppSection\Shift\UI\API\Controllers;

use App\Containers\AppSection\Shift\Actions\GetShiftCalendarAction;
use App\Containers\AppSection\Shift\UI\API\Requests\GetShiftCalendarRequest;
use App\Ship\Parents\Controllers\ApiController;
use Illuminate\Http\JsonResponse;

final class GetShiftCalendarController extends ApiController
{
    public function __invoke(GetShiftCalendarRequest $request): JsonResponse
    {
        $year = (int) $request->input('year', date('Y'));
        $month = (int) $request->input('month', date('n'));

        $calendar = app(GetShiftCalendarAction::class)->run($year, $month);

        // Transform shifts grouped by date
        $days = [];
        foreach ($calendar['days'] as $date => $shifts) {
            $days[$date] = $shifts->map(function ($shift) {
                $template = $shift->template;
                return [
                    'id'             => $shift->getHashedKey(),
                    'shift_number'   => $shift->shift_number,
                    'start_time'     => $shift->start_time ? substr($shift->start_time, 0, 5) : null,
                    'end_time'       => $shift->end_time ? substr($shift->end_time, 0, 5) : null,
                    'template_name'  => $template?->name,
                    'template_color' => $template?->color,
                ];
            })->values();
        }

        return response()->json([
            'data' => [
                'year'  => $calendar['year'],
                'month' => $calendar['month'],
                'days'  => $days,
            ],
        ]);
    }
}
