<?php

namespace App\Containers\AppSection\AuditLog\Models;

use App\Containers\AppSection\Setting\Models\Setting;
use App\Containers\AppSection\User\Models\User as AdminUser;
use App\Ship\Parents\Models\Model as ParentModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\MorphTo;

final class AuditHistory extends ParentModel
{
    use MassPrunable;

    private const DEFAULT_RETENTION_DAYS = 30;

    protected $table = 'audit_histories';

    protected $fillable = [
        'user_agent',
        'ip_address',
        'module',
        'action',
        'user_id',
        'user_type',
        'actor_id',
        'actor_type',
        'reference_id',
        'reference_name',
        'type',
        'request',
    ];

    public function user(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): MorphTo
    {
        return $this->morphTo('actor');
    }

    public function getUserNameAttribute(): string
    {
        if (!$this->user_type || !class_exists($this->user_type)) {
            return 'System';
        }

        if (!$this->user) {
            return 'System';
        }

        return (string) ($this->user->name ?? 'System');
    }

    public function getActorNameAttribute(): string
    {
        if (!$this->actor_type || !class_exists($this->actor_type)) {
            return 'System';
        }

        if (!$this->actor) {
            return 'System';
        }

        return (string) ($this->actor->name ?? 'System');
    }

    public function getUserTypeLabelAttribute(): string
    {
        if (!$this->user_type || !class_exists($this->user_type)) {
            return 'System';
        }

        return match ($this->user_type) {
            AdminUser::class => 'Admin',
            default => 'System',
        };
    }

    public function getActorTypeLabelAttribute(): string
    {
        if (!$this->actor_type || !class_exists($this->actor_type)) {
            return 'System';
        }

        return match ($this->actor_type) {
            AdminUser::class => 'Admin',
            default => 'System',
        };
    }

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
            ->where('key', 'audit_log_data_retention_period')
            ->value('value');

        if (is_numeric($value)) {
            return max(0, (int) $value);
        }

        return self::DEFAULT_RETENTION_DAYS;
    }
}
