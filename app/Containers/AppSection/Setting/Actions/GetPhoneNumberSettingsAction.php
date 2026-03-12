<?php

namespace App\Containers\AppSection\Setting\Actions;

use App\Containers\AppSection\Setting\Tasks\GetSettingsTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class GetPhoneNumberSettingsAction extends ParentAction
{
    public function __construct(
        private readonly GetSettingsTask $getSettingsTask,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        return $this->getSettingsTask->run([
            'phone_number_enable_country_code',
            'phone_number_available_countries',
            'phone_number_min_length',
            'phone_number_max_length',
        ]);
    }
}
