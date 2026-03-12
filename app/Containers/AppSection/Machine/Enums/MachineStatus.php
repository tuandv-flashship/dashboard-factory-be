<?php

namespace App\Containers\AppSection\Machine\Enums;

enum MachineStatus: string
{
    case ONLINE = 'online';
    case OFFLINE = 'offline';
    case MAINTENANCE = 'maintenance';
}
