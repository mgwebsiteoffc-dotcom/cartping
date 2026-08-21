<?php

namespace App\Services\Shopify;

use App\Models\Plan;
use App\Models\Store;

/**
 * Handles subscription billing through the Shopify Billing API.
 *
 * The merchant subscribes to a plan; billing is collected by Shopify via a
 * recurring application charge. On acceptance, Shopify redirects back to our
 * return_url with ?charge_id=..., which we verify and apply to the store.
 */
class ShopifyBilling
{
    public const FREE_PLAN_CODE = 'free';

    /**
     * Start a subscription for a store. Returns the confirmation_url to send the
     * merchant to, or null if the plan requires no charge (free).
     */
    public function subscribe(Store $store, string $planCode): ?string
    {
        $plan = Plan::where('code', $planCode)->first();

        if (! $plan) {
            abort(404, 'Unknown plan.');
        }

        // Free plan → no Shopify charge, just assign it.
        if ((float) $plan->price_monthly <= 0) {
            $this->applyPlan($store, $plan);

            return null;
        }

        $client = ShopifyClient::for($store);

        $charge = $client->createRecurringCharge(
            'CartPing '.$plan->name,
            $plan->price_monthly,
            route('billing.callback', ['plan' => $plan->code]),
            trialDays: $plan->limit('trial_days') ?: null
        );

        // Remember the pending plan so the callback can apply it after Shopify
        // confirms the charge was accepted.
        cache()->put("billing_pending_{$store->id}", $plan->code, now()->addDay());

        return $charge['confirmation_url'] ?? null;
    }

    /**
     * Verify a Shopify charge and apply the plan to the store.
     */
    public function verifyCallback(Store $store, int $chargeId, string $planCode): bool
    {
        $client = ShopifyClient::for($store);
        $charge = $client->getRecurringCharge($chargeId);

        if (($charge['status'] ?? '') !== 'active') {
            return false;
        }

        $plan = Plan::where('code', $planCode)->first();
        if (! $plan) {
            return false;
        }

        $this->applyPlan($store, $plan, $chargeId);

        cache()->forget("billing_pending_{$store->id}");

        return true;
    }

    protected function applyPlan(Store $store, Plan $plan, ?int $chargeId = null): void
    {
        $store->update([
            'plan_id' => $plan->id,
            'plan_expires_at' => $plan->price_monthly > 0
                ? now()->addMonth()
                : null,
            'disabled_at' => null,
            'settings' => array_merge($store->settings ?? [], [
                'shopify_charge_id' => $chargeId,
                'billing_plan' => $plan->code,
            ]),
        ]);
    }
}
