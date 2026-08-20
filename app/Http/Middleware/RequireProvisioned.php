<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Guards routes that require the store to have completed setup (a WhatsApp
 * connection + agent config) before use. Redirects to onboarding otherwise.
 */
class RequireProvisioned
{
    public function handle(Request $request, Closure $next)
    {
        $store = $request->user('store');

        if ($store && ! $store->isProvisioned()) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Store not provisioned'], 409);
            }

            return redirect()->route('onboarding.index');
        }

        return $next($request);
    }
}
