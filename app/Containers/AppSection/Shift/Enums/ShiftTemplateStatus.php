<?php

namespace App\Containers\AppSection\Shift\Enums;

enum ShiftTemplateStatus: string
{
    case ACTIVE = 'active';       // Đang sử dụng
    case INACTIVE = 'inactive';   // Tạm dừng
}
