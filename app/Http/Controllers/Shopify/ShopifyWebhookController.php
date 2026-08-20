<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Events\OrderEventReceived;
use Illuminate\Http\Request;

/**
 * Receives Shopify topic webhooks (verified by middleware), dispatches the
 * event for queued processing and immediately acks.
 */
class ShopifyWebhookController extends Controller
{
    public function handle(Request $request, string $topic)
    {
        $store = $request->attributes->get('shopify_store');
        $eventId = $request->attributes->get('shopify_event_id');

        OrderEventReceived::dispatch($store, $topic, $request->json()->all(), $eventId);

        return response()->json(['received' => true]);
    }
}
