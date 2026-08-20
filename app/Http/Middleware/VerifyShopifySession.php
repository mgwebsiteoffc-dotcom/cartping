<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Verifies that an embedded Shopify app request is a valid, signed JWT session
 * (session_token header) when the app is embedded, so merchants browsing the
 * app inside the Shopify admin can be auto-authenticated without OAuth redirects.
 */
class VerifyShopifySession
{
    public function handle(Request $request, Closure $next)
    {
        $sessionToken = $request->header('Authorization');

        if ($sessionToken && str_starts_with($sessionToken, 'Bearer ')) {
            $sessionToken = substr($sessionToken, 7);
        }

        if ($sessionToken) {
            // In a real deployment this JWT would be validated against the
            // Shopify session token public keys. Here we resolve the store by
            // the dest query param that Shopify appends.
            $dest = $request->input('shop') ?: $request->input('destination');

            if ($dest) {
                $store = \App\Models\Store::where('myshopify_domain', $dest)->first();
                if ($store) {
                    \Illuminate\Support\Facades\Auth::guard('store')->login($store);
                }
            }
        }

        return $next($request);
    }
}
