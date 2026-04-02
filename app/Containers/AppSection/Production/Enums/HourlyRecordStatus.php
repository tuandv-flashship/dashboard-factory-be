<?php

namespace App\Containers\AppSection\Production\Enums;

enum HourlyRecordStatus: string
{
    case Pending   = 'pending';
    case Active    = 'active';
    case Completed = 'completed';
}
