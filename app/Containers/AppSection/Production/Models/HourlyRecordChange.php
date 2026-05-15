<?php

namespace App\Containers\AppSection\Production\Models;

use App\Containers\AppSection\Setting\Models\Setting;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class HourlyRecordChange extends ParentModel
{
    use MassPrunable;

    private const DEFAULT_RETENTION_DAYS = 180;

    public $timestamps = false;

    protected $table = 'hourly_record_changes';

    protected $fillable = [
        'hourly_record_id',
        'user_id',
        'user_name',
        'changes',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'changes'    => 'array',
        'created_at' => 'datetime',
    ];

    // ── Relations ──────────────────────────────────────

    public function hourlyRecord(): BelongsTo
    {
        return $this->belongsTo(HourlyRecord::class, 'hourly_record_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // ── Pruning ────────────────────────────────────────

    /**
     * Auto-prune based on Setting 'hourly_change_retention_days'.
     * Default: 180 days. Set = 0 to keep forever.
     */
    public function prunable(): Builder
    {
        $days = $this->getRetentionDays();

        if ($days === 0) {
            return $this->newQuery()->whereRaw('1 = 0');
        }

        return $this->newQuery()->where('created_at', '<', now()->subDays($days));
    }

    private function getRetentionDays(): int
    {
        $value = Setting::query()
            ->where('key', 'hourly_change_retention_days')
            ->value('value');

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        return self::DEFAULT_RETENTION_DAYS;
    }
}
