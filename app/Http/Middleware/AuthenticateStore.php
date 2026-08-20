<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Http\Request;

class AuthenticateStore extends Authenticate
{
    protected function authenticate($request, array $guards)
    {
        if ($this->auth->guard('store')->check()) {
            return $this->auth->shouldUse('store');
        }

        $this->unauthenticated($request, ['store']);
    }

    protected function redirectTo(Request $request): ?string
    {
        if (! $request->expectsJson()) {
            return route('auth.signin');
        }

        return null;
    }
}
