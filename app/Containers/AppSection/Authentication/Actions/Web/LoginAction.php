<?php

namespace App\Containers\AppSection\Authentication\Actions\Web;

use App\Ship\Parents\Actions\Action as ParentAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

final class LoginAction extends ParentAction
{
    public function run(string $username, string $password, bool $remember): RedirectResponse
    {
        $username = strtolower($username);
        $field = filter_var($username, FILTER_VALIDATE_EMAIL) !== false ? 'email' : 'username';

        $credentials = [
            $field => static fn (Builder $query): Builder => $query
                ->where($field, $username),
            'password' => $password,
        ];

        if (Auth::guard('web')->attempt($credentials, $remember)) {
            session()?->regenerate();

            return redirect()->intended();
        }

        return back()->withErrors([
            'username' => __('auth.failed'),
        ])->onlyInput('username');
    }
}
