<?php

namespace App\Containers\AppSection\Media\Models;

use App\Ship\Parents\Models\Model as ParentModel;

final class MediaSetting extends ParentModel
{
    protected $table = 'media_settings';

    protected $fillable = [
        'key',
        'value',
        'media_id',
        'user_id',
    ];

    protected $casts = [
        'value' => 'array',
    ];
}
