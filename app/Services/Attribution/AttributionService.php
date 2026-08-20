<?php

namespace App\Services\Attribution;

use App\Jobs\SendMetaConversionEvent;
use App\Models\AnalyticsEvent;
use App\Models\ClickEvent;
use App\Models\Conversation;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Support\Carbon;

/**
 * Full-funnel CTWA attribution:
 *   ad click (fingerprint) -> WhatsApp conversation -> Shopify order -> Meta CAPI.
 *
 * The conversion window is configurable (default 24h). When an order arrives
 * and a click fingerprint matches, we attribute revenue and fire a Purchase
 * event back to Meta for ROAS measurement.
 */
class AttributionService
{
    public function __construct(protected int $windowHours = 24)
    {
        $this->windowHours = (int) config('attribution.window_hours', 24);
    }

    /**
     * Called when a conversation is opened (a WhatsApp lead after an ad click).
     */
    public function markConversationFromAd(Store $store, Conversation $conversation, ?string $fingerprint): void
    {
        if (! $fingerprint) {
            return;
        }

        $click = ClickEvent::where('store_id', $store->id)
            ->where('fingerprint', $fingerprint)
            ->where('converted', false)
            ->where('clicked_at', '>=', now()->subHours($this->windowHours))
            ->latest('clicked_at')
            ->first();

        if (! $click) {
            return;
        }

        $conversation->update([
            'ctwa_click_id' => $click->id,
            'source' => 'ctwa',
        ]);

        // Opt-in for the ad lead qualifies for a marketing conversation.
        $conversation->contact?->grantOptIn('ctwa');

        dispatch(new SendMetaConversionEvent($store, 'Lead', [
            'value' => null,
            'custom_data' => ['ctwa_ad' => $click->ctwa_ad_id, 'click_id' => $click->click_id],
        ]))->onQueue('low');
    }

    /**
     * Called when an order is placed/synced. Matches to a click by fingerprint
     * (or by the conversation's ctwa_click_id) and attributes revenue.
     */
    public function attributeOrder(Store $store, Order $order, ?string $fingerprint = null, ?int $conversationId = null): ?ClickEvent
    {
        $click = null;

        if ($conversationId) {
            $conversation = Conversation::find($conversationId);
            if ($conversation?->ctwa_click_id) {
                $click = ClickEvent::find($conversation->ctwa_click_id);
            }
        }

        if (! $click && $fingerprint) {
            $click = ClickEvent::where('store_id', $store->id)
                ->where('fingerprint', $fingerprint)
                ->where('converted', false)
                ->where('clicked_at', '>=', now()->subHours($this->windowHours))
                ->latest('clicked_at')
                ->first();
        }

        if (! $click) {
            return null;
        }

        $click->update([
            'converted' => true,
            'order_id' => $order->shopify_order_id,
            'order_total' => $order->total_price,
        ]);

        AnalyticsEvent::record('revenue.attributed', [
            'store_id' => $store->id,
            'ad_id' => $click->ctwa_ad_id,
            'contact_id' => $order->contact_id,
            'value' => $order->total_price,
            'payload' => [
                'order_number' => $order->order_number,
                'click_id' => $click->click_id,
                'window_hours' => $this->windowHours,
            ],
        ]);

        // Report the purchase to Meta CAPI for ROAS measurement.
        dispatch(new SendMetaConversionEvent($store, 'Purchase', [
            'value' => $order->total_price,
            'currency' => $order->currency,
            'custom_data' => [
                'ctwa_ad' => $click->ctwa_ad_id,
                'click_id' => $click->click_id,
                'order_id' => (string) $order->shopify_order_id,
            ],
        ]))->onQueue('low');

        return $click;
    }
}
