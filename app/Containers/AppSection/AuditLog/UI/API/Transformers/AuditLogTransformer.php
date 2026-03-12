<?php

namespace App\Containers\AppSection\AuditLog\UI\API\Transformers;

use App\Containers\AppSection\AuditLog\Models\AuditHistory;
use App\Containers\AppSection\User\Models\User;
use App\Ship\Parents\Transformers\Transformer as ParentTransformer;

final class AuditLogTransformer extends ParentTransformer
{
    public function transform(AuditHistory $history): array
    {
        return [
            'type' => $history->getResourceKey(),
            'id' => $history->getHashedKey(),
            'module' => $history->module,
            'action' => $history->action,
            'status' => $history->type,
            'reference_id' => $history->reference_id,
            'reference_name' => $history->reference_name,
            'user_id' => $history->user_id,
            'user_type' => $history->user_type,
            'user_name' => $this->resolveName($history->user, $history->user_type),
            'user_type_label' => $this->resolveTypeLabel($history->user_type),
            'actor_id' => $history->actor_id,
            'actor_type' => $history->actor_type,
            'actor_name' => $this->resolveName($history->actor, $history->actor_type),
            'actor_type_label' => $this->resolveTypeLabel($history->actor_type),
            'ip_address' => $history->ip_address,
            'user_agent' => $history->user_agent,
            'request' => $this->decodeRequest($history->request),
            'created_at' => $history->created_at?->toISOString(),
            'updated_at' => $history->updated_at?->toISOString(),
        ];
    }

    private function resolveName(mixed $related, string|null $type): string
    {
        if (! $type || ! class_exists($type)) {
            return 'System';
        }

        if (! $related) {
            return 'System';
        }

        return (string) ($related->name ?? 'System');
    }

    private function resolveTypeLabel(string|null $type): string
    {
        if (! $type || ! class_exists($type)) {
            return 'System';
        }

        return match ($type) {
            User::class => 'Admin',
            default => 'Customer',
        };
    }

    private function decodeRequest(string|null $request): array
    {
        if (! $request) {
            return [];
        }

        $decoded = json_decode($request, true);

        return is_array($decoded) ? $decoded : [];
    }
}
