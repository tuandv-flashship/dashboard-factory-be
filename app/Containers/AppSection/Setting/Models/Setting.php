<?php

namespace App\Containers\AppSection\Setting\Models;

use App\Ship\Parents\Models\Model as ParentModel;

final class Setting extends ParentModel
{
    protected $table = 'settings';

    protected $fillable = [
        'key',
        'value',
    ];
}
