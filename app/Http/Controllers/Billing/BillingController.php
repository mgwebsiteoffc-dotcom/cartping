<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Services\Shopify\ShopifyBilling;
use Illuminate\Http\Request;

/**
 * Shopify Billing API integration.
 *
 * subscribe : authenticated store subscribes to a plan → redirects to Shopify
 *             confirmation (or applies the free plan immediately).
 * callback  : Shopify redirects back after the merchant accepts the charge →
 *             verify and apply the plan.
 */
class BillingController extends Controller
{
    public function __construct(protected ShopifyBilling $billing)
    {
    }

    public function subscribe(Request $request, string $plan)
    {
        $store = request()->user('store');

        $confirmationUrl = $this->billing->subscribe($store, $plan);

        if (! $confirmationUrl) {
            return redirect()->route('dashboard.index')
                ->with('status', 'You are on the '.ucfirst($plan).' plan.');
        }

        return redirect()->away($confirmationUrl);
    }

    public function callback(Request $request)
    {
        $chargeId = (int) $request->input('charge_id');
        $planCode = $request->input('plan');

        // Shopify appends ?shop= to the return_url in embedded context.
        $shop = $request->input('shop');
        $store = $shop
            ? Store::where('myshopify_domain', strtolower($shop))->first()
            : request()->user('store');

        if (! $store || ! $chargeId || ! $planCode) {
            abort(400, 'Missing billing parameters.');
        }

        $ok = $this->billing->verifyCallback($store, $chargeId, $planCode);

        if ($request->expectsJson()) {
            return response()->json(['ok' => $ok]);
        }

        return redirect()->route('dashboard.index')
            ->with('status', $ok ? 'Plan activated.' : 'Charge was not accepted.');
    }
}
