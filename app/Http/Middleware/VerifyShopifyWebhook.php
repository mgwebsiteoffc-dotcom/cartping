<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Verifies that an incoming Shopify webhook carries a valid HMAC signature
 * and that the shop domain matches a known store. Records the raw event so it
 * can be dispatched to a queue idempotently.
 */
class VerifyShopifyWebhook
{
    public function handle(Request $request, Closure $next)
    {
        $payload = $request->getContent();

        $hmac = $request->header('X-Shopify-Hmac-Sha256');
        $shop = $request->header('X-Shopify-Shop-Domain');
        $topic = $request->header('X-Shopify-Topic');

        $store = Store::where('myshopify_domain', $shop)->first();

        if (! $store) {
            Log::channel('whatsapp')->warning('Shopify webhook from unknown store', ['shop' => $shop]);
            return response()->json(['error' => 'Unknown shop'], 401);
        }

        $secret = $store->shopifyConnection?->isOauth()
            ? config('shopify.api_secret')
            : null;

        if ($secret && $hmac) {
            $computed = base64_encode(hash_hmac('sha256', $payload, $secret, true));
            if (! hash_equals($computed, $hmac)) {
                return response()->json(['error' => 'Invalid signature'], 401);
            }
        }

        // Persist the raw event for idempotent, queued processing.
        $event = \App\Models\OrderEvent::create([
            'store_id' => $store->id,
            'shop' => $shop,
            'topic' => $topic,
            'shopify_order_id' => $request->input('id'),
            'api_version' => $request->header('X-Shopify-Api-Version'),
            'shopify_domain' => $shop,
            'payload' => $request->json()->all(),
        ]);

        $request->attributes->set('shopify_store', $store);
        $request->attributes->set('shopify_event_id', $event->id);
        $request->attributes->set('shopify_topic', $topic);

        return $next($request);
    }
}
