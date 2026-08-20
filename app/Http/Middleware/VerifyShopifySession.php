<?php

namespace App\Http\Middleware;

use App\Services\Shopify\ShopifySessionToken;
use Closure;
use Illuminate\Http\Request;

/**
 * Embedded Shopify app authentication.
 *
 * Validates the Shopify session token (JWT) that Shopify provides on embedded
 * page loads (id_token / session query param or Authorization header) and
 * auto-logs the store in, so merchants never see a login page inside the admin.
 */
class VerifyShopifySession
{
    public function __construct(protected ShopifySessionToken $sessionToken)
    {
    }

    public function handle(Request $request, Closure $next)
    {
        $this->sessionToken->authenticate($request);

        return $next($request);
    }
}
