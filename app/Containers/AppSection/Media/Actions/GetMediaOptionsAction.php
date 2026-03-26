<?php

namespace App\Containers\AppSection\Media\Actions;

use App\Ship\Parents\Actions\Action as ParentAction;

final class GetMediaOptionsAction extends ParentAction
{
    /**
     * Return media configuration options for the frontend.
     *
     * @return array<string, mixed>
     */
    public function run(): array
    {
        return [
            'folder_colors' => config('media.folder_colors', []),
        ];
    }
}
