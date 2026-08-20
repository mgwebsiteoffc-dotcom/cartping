<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Conversation;
use App\Models\Order;
use App\Services\Analytics\AnalyticsService;

class ShopifyController extends Controller
{
    public function dashboard(AnalyticsService $analytics)
    {
        $store = request()->user('store');

        $metrics = $analytics->dashboard($store, 30);

        $conversationsOpen = Conversation::where('store_id', $store->id)
            ->whereNotIn('status', ['resolved', 'closed'])->count();

        $ordersToday = Order::where('store_id', $store->id)
            ->where('placed_at', '>=', now()->startOfDay())->count();

        return view('dashboard.index', [
            'store' => $store,
            'metrics' => $metrics,
            'conversationsOpen' => $conversationsOpen,
            'ordersToday' => $ordersToday,
            'onboardingStep' => $store->onboarding_step,
            'whatsappConnected' => (bool) $store->whatsappConnection?->is_connected,
            'shopifyConnected' => (bool) $store->shopifyConnection?->access_token,
        ]);
    }

    /**
     * Shopify connection & token management. Shows the auth mode, whether the
     * token is the modern expiring offline token, when it expires, and lets the
     * merchant re-authorize via OAuth (or update a Custom App token).
     */
    public function settings()
    {
        $store = request()->user('store');
        $connection = $store->shopifyConnection;

        $client = null;
        $shop = null;

        if ($connection?->access_token) {
            try {
                $client = \App\Services\Shopify\ShopifyClient::for($store);
                $shop = $client->shopInfo();
            } catch (\Throwable $e) {
                $shop = null;
            }
        }

        return view('settings.shopify', [
            'store' => $store,
            'connection' => $connection,
            'shop' => $shop,
            'oauthConfigured' => (new \App\Services\Shopify\ShopifyOAuth())->isConfigured(),
        ]);
    }
}
