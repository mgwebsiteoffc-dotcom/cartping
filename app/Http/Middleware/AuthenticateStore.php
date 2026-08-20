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
            // Embedded admin / installed-shop context → go straight to Shopify OAuth
            // (no login page). Standalone access → the normal sign-in page.
            if (config('shopify.embedded') && ($request->has('id_token') || $request->header('Authorization'))) {
                $shop = $request->input('shop');
                if ($shop) {
                    return url()->route('auth.shopify', ['shop' => $shop]);
                }
            }

            return route('auth.signin');
        }

        return null;
    }
}
