<?php

namespace App\Containers\AppSection\RequestLog\Models;

use App\Containers\AppSection\Setting\Models\Setting;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;

final class RequestLog extends ParentModel
{
    use MassPrunable;

    private const DEFAULT_RETENTION_DAYS = 30;

    protected $table = 'request_logs';

    protected $fillable = [
        'url',
        'status_code',
        'count',
        'user_id',
        'referrer',
    ];

    protected $casts = [
        'referrer' => 'json',
        'user_id' => 'json',
    ];

    public function prunable(): Builder
    {
        $days = $this->getRetentionDays();

        if ($days === 0) {
            return $this->query()->where('id', '<', 0);
        }

        return $this->query()->where('created_at', '<', now()->subDays($days));
    }

    private function getRetentionDays(): int
    {
        $value = Setting::query()
            ->where('key', 'request_log_data_retention_period')
            ->value('value');

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        return self::DEFAULT_RETENTION_DAYS;
    }
}
