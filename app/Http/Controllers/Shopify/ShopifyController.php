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
        ]);
    }
}
