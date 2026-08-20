<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Shopify\ShopifyOAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Mode 1: public Shopify App OAuth. Redirects the merchant to Shopify, then
 * completes the callback, persists the connection and auto-logs the store in.
 */
class ShopifyAuthController extends Controller
{
    public function __construct(protected ShopifyOAuth $oauth)
    {
    }

    public function redirect(Request $request)
    {
        if (! $this->oauth->isConfigured()) {
            return redirect()->route('auth.manual')->withErrors(
                'Public app OAuth is not configured. Use the manual Custom App connection instead.'
            );
        }

        $shop = $this->normalizeShop($request->query('shop', $request->input('shop')));

        if (! $shop) {
            return redirect()->route('auth.manual')->withErrors('Please provide a valid Shopify store URL.');
        }

        return redirect()->away($this->oauth->authorizeUrl($shop));
    }

    /**
     * Exchange a Shopify embedded session token for a logged-in store session.
     * Called by the embedded frontend (via App Bridge) when cookies are blocked
     * in the admin iframe. Returns 200 + shop on success, 401 otherwise.
     */
    public function session(Request $request, \App\Services\Shopify\ShopifySessionToken $sessionToken)
    {
        $token = $request->input('session_token')
            ?: ($request->header('Authorization') && str_starts_with($request->header('Authorization'), 'Bearer ')
                ? substr($request->header('Authorization'), 7)
                : $request->query('id_token'));

        if (! $token) {
            return response()->json(['ok' => false], 401);
        }

        $store = $sessionToken->resolveStore($token);

        if (! $store) {
            return response()->json(['ok' => false], 401);
        }

        Auth::guard('store')->login($store);

        return response()->json([
            'ok' => true,
            'shop' => $store->myshopify_domain,
            'dashboard' => url()->route('dashboard.index'),
        ]);
    }

    /**
     * Accept "yourshop", "yourshop.myshopify.com", or a full https:// URL and
     * normalize to "yourshop.myshopify.com".
     */
    protected function normalizeShop(?string $shop): ?string
    {
        if (! $shop) {
            return null;
        }

        $shop = trim($shop);

        // Strip scheme and path.
        if (preg_match('#^https?://([^/]+)#i', $shop, $m)) {
            $shop = $m[1];
        }

        // Strip port.
        $shop = preg_replace('#:\d+$#', '', $shop);

        if (! preg_match('/\.myshopify\.com$/i', $shop)) {
            $shop .= '.myshopify.com';
        }

        $shop = strtolower($shop);

        return preg_match('/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/', $shop) ? $shop : null;
    }

    public function callback(Request $request)
    {
        $code = $request->query('code');
        $shop = $request->query('shop');
        $state = $request->query('state');

        if (! $code || ! $shop || ! $state) {
            abort(400, 'Missing OAuth parameters.');
        }

        if (! $this->oauth->verifyCallback($request->query())) {
            abort(403, 'Invalid OAuth signature.');
        }

        if (! $this->oauth->verifyState($shop, $state)) {
            abort(403, 'Invalid state.');
        }

        $token = $this->oauth->exchangeCode($shop, $code);

        $store = $this->oauth->persistConnection(
            $shop,
            $token,
            implode(',', config('shopify.scopes'))
        );

        Auth::guard('store')->login($store);

        // Embedded app: redirect back into the Shopify admin. Shopify will load
        // our app inside the admin iframe and append a fresh session token, which
        // auto-authenticates the store (cookies are blocked in the iframe).
        // Uses the current admin URL format: admin.shopify.com/store/{shop}/apps/{key}
        if ($request->query('embedded') || config('shopify.embedded')) {
            $shopName = str_replace('.myshopify.com', '', $shop);
            $appUrl = 'https://admin.shopify.com/store/'.$shopName.'/apps/'.config('shopify.api_key');

            return redirect()->away($appUrl);
        }

        return redirect()->route('dashboard.index');
    }

    /**
     * Normalize a shop domain (e.g. "mannatgupta-stagee.myshopify.com").
     */
    protected function shopName(string $shop): string
    {
        return str_replace('.myshopify.com', '', strtolower(trim($shop)));
    }
}
