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
            // Embedded admin context is signalled by the `host` query param that
            // Shopify appends (plus id_token/session). Go straight to Shopify OAuth
            // so the merchant never sees our login page inside the admin.
            $embedded = config('shopify.embedded')
                && ($request->has('host') || $request->has('id_token') || $request->has('session'));

            if ($embedded) {
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
