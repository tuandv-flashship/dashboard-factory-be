<?php

namespace App\Containers\AppSection\Setting\Actions;

use App\Containers\AppSection\Media\Supports\MediaSettingsStore;
use App\Ship\Parents\Actions\Action as ParentAction;

final class GetMediaSettingsAction extends ParentAction
{
    public function __construct(private readonly MediaSettingsStore $settingsStore)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function run(): array
    {
        return $this->settingsStore->all();
    }
}
