<?php

namespace App\Containers\AppSection\AuditLog\Listeners;

use App\Containers\AppSection\AuditLog\Models\AuditHistory;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Auth\Events\Logout;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Throwable;

final class CustomerLogoutListener
{
    public function __construct(private readonly Request $request)
    {
    }

    public function handle(Logout $event): void
    {
        $user = $event->user;

        if (! $user instanceof Authenticatable) {
            return;
        }

        $module = $user instanceof User ? 'from the admin panel' : 'from the customer portal';

        try {
            AuditHistory::query()->create([
                'user_agent' => $this->request->userAgent(),
                'ip_address' => $this->request->ip(),
                'module' => $module,
                'action' => 'logged out',
                'user_id' => $user->getKey(),
                'user_type' => $user::class,
                'actor_id' => $user->getKey(),
                'actor_type' => $user::class,
                'reference_id' => $user->getKey(),
                'reference_name' => $user->name ?? '',
                'type' => 'info',
            ]);
        } catch (Throwable) {
            // Ignore audit failures.
        }
    }
}
