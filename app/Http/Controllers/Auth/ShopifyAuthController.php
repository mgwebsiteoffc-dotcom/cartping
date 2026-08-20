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

        $shop = $request->query('shop');

        if (! $shop || ! preg_match('/^[a-zA-Z0-9][a-zA-Z0-9\-]*\.myshopify\.com$/', $shop)) {
            return redirect()->route('auth.manual');
        }

        return redirect()->away($this->oauth->authorizeUrl($shop));
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
