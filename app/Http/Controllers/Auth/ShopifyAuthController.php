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

        return redirect()->route('onboarding.index');
    }
}
