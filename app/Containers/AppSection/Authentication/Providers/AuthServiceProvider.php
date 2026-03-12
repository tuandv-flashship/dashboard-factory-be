<?php

namespace App\Containers\AppSection\Authentication\Providers;

use App\Ship\Parents\Providers\ServiceProvider as ParentServiceProvider;
use Illuminate\Auth\SessionGuard;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Guards\TokenGuard;
use App\Containers\AppSection\User\Models\User;

final class AuthServiceProvider extends ParentServiceProvider
{
    public function boot(): void
    {
        Gate::before(static function ($user): bool|null {
            if ($user instanceof User && $user->isSuperAdmin()) {
                return true;
            }

            return null;
        });

        $method = function () {
            foreach (array_keys(config('auth.guards')) as $guard) {
                if (Auth::guard($guard)->check()) {
                    return $guard;
                }
            }

            return null;
        };
        /*
         * Get the current logged-in user guard.
         *
         * @return string|null
         */
        SessionGuard::macro('activeGuard', $method);
        TokenGuard::macro('activeGuard', $method);
    }
}
