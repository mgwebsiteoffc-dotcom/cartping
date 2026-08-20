<?php

namespace App\Events;

use App\Models\Store;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A Shopify order lifecycle event (orders/create, orders/fulfilled,
 * orders/cancelled, fulfillment_events/create) was received via webhook.
 * A single listener evaluates all matching automations for the store.
 */
class OrderEventReceived
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Store $store,
        public string $topic,
        public array $payload,
        public string $orderEventId,
    ) {
    }
}
