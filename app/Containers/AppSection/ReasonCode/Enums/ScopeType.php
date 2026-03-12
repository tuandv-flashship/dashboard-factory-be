<?php

namespace App\Containers\AppSection\ReasonCode\Enums;

enum ScopeType: string
{
    case GLOBAL = 'global';
    case PER_DEPARTMENT = 'per_department';
    case PER_LINE_DEPARTMENT = 'per_line_department';
}
