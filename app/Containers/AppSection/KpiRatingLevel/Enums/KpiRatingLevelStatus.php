<?php

namespace App\Containers\AppSection\KpiRatingLevel\Enums;

enum KpiRatingLevelStatus: string
{
    case PENDING = 'pending';       // Chưa áp dụng
    case ACTIVE = 'active';         // Đang áp dụng
    case EXPIRED = 'expired';       // Hết hiệu lực
}
