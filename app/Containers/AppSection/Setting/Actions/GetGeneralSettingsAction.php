<?php

namespace App\Containers\AppSection\Setting\Actions;

use App\Containers\AppSection\Setting\Tasks\GetSettingsTask;
use App\Ship\Parents\Actions\Action as ParentAction;

final class GetGeneralSettingsAction extends ParentAction
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
            'admin_email',
            'time_zone',
            'enable_send_error_reporting_via_email',
            'locale_direction',
            'locale',
            'redirect_404_to_homepage',
            'request_log_data_retention_period',
            'audit_log_data_retention_period',
        ]);
    }
}
