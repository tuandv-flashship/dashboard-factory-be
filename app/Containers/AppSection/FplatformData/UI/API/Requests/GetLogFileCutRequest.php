<?php

namespace App\Containers\AppSection\FplatformData\UI\API\Requests;

use App\Ship\Parents\Requests\Request as ParentRequest;

final class GetLogFileCutRequest extends ParentRequest
{
    public function rules(): array
    {
        return [
            'start_log' => ['required', 'date'],
            'end_log'   => ['required', 'date', 'after:start_log'],
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
        return [
            'start_log.required' => 'Vui lòng nhập thời gian bắt đầu.',
            'end_log.required'   => 'Vui lòng nhập thời gian kết thúc.',
            'end_log.after'      => 'Thời gian kết thúc phải sau thời gian bắt đầu.',
        ];
    }
}
