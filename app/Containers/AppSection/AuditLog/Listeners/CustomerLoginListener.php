<?php

namespace App\Containers\AppSection\AuditLog\Listeners;

use App\Containers\AppSection\AuditLog\Models\AuditHistory;
use App\Containers\AppSection\User\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Throwable;

final class CustomerLoginListener
{
    public function __construct(private readonly Request $request)
    {
    }

    public function handle(Login $event): void
    {
        $user = $event->user;

        if (! $user instanceof Authenticatable || $user instanceof User) {
            return;
        }

        try {
            AuditHistory::query()->create([
                'user_agent' => $this->request->userAgent(),
                'ip_address' => $this->request->ip(),
                'module' => 'to the customer portal',
                'action' => 'logged in',
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
