<?php

namespace App\Containers\AppSection\FplatformData\UI\API\Requests;

use App\Containers\AppSection\FplatformData\Enums\HourlyMetricType;
use App\Containers\AppSection\FplatformData\Enums\Team;
use App\Ship\Parents\Requests\Request as ParentRequest;
use Illuminate\Validation\Rules\Enum;

final class GetHourlyMetricsRequest extends ParentRequest
{
    public function rules(): array
    {
        $validTeams = array_map(fn (Team $t) => $t->value, Team::hourlyTeams());

        return [
            'team'        => ['required', 'in:' . implode(',', $validTeams)],
            'metric'      => ['required', new Enum(HourlyMetricType::class)],
            'start_shift' => ['required', 'date'],
            'end_shift'   => ['required', 'date', 'after:start_shift'],
        ];
    }

    public function authorize(): bool
    {
        $user = $this->user();

        if (!$user) {
            return false;
        }

        return $user->hasPermissionTo('shifts.index')
            || $user->hasRole('admin');
    }

    public function messages(): array
    {
        $validTeams = array_map(fn (Team $t) => $t->value, Team::hourlyTeams());

        return [
            'team.required'        => 'Vui lòng chọn team.',
            'team.in'              => 'Team hợp lệ: ' . implode(', ', $validTeams),
            'metric.required'      => 'Vui lòng chọn metric type.',
            'start_shift.required' => 'Vui lòng nhập thời gian bắt đầu ca.',
            'end_shift.required'   => 'Vui lòng nhập thời gian kết thúc ca.',
            'end_shift.after'      => 'Thời gian kết thúc phải sau thời gian bắt đầu.',
        ];
    }

    /**
     * Additional validation: check team+metric compatibility.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $team = Team::tryFrom($this->input('team'));
            $metric = HourlyMetricType::tryFrom($this->input('metric'));

            if ($team && $metric && !$team->supportsMetric($metric)) {
                $validator->errors()->add(
                    'metric',
                    "Team '{$team->value}' không hỗ trợ metric '{$metric->value}'."
                );
            }
        });
    }
}
