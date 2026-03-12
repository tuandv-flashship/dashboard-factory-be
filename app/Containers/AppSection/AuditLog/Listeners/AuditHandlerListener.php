<?php

namespace App\Containers\AppSection\AuditLog\Listeners;

use App\Containers\AppSection\AuditLog\Events\AuditHandlerEvent;
use App\Containers\AppSection\AuditLog\Models\AuditHistory;
use App\Containers\AppSection\Setting\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

final class AuditHandlerListener implements \Illuminate\Contracts\Queue\ShouldQueue
{
    use \Illuminate\Queue\InteractsWithQueue;

    public function __construct()
    {
    }

    public function handle(AuditHandlerEvent $event): void
    {
        try {
            $module = strtolower(Str::afterLast($event->module, '\\'));
            
            $userId = (int) $event->referenceUser;
            $userType = $event->userType;

            $data = [
                'user_agent' => $event->userAgent,
                'ip_address' => $event->ip,
                'module' => $module,
                'action' => $event->action,
                'user_id' => $userId,
                'user_type' => $userType,
                'actor_id' => $userId, 
                'actor_type' => $userType,
                'reference_id' => $event->referenceId,
                'reference_name' => $event->referenceName ?? '',
                'type' => $event->type,
            ];

            if (! in_array($event->action, ['loggedin', 'password'], true)) {
                $data['request'] = json_encode($event->input);
            }

            AuditHistory::query()->create($data);
        } catch (Throwable) {
            // Avoid impacting the request if audit logging fails.
        }
    }
}
