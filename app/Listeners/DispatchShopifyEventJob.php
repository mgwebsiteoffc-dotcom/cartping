<?php

namespace App\Listeners;

use App\Events\OrderEventReceived;
use App\Jobs\ProcessShopifyOrderEvent;
use App\Models\OrderEvent;

class DispatchShopifyEventJob
{
    public function handle(OrderEventReceived $event): void
    {
        $orderEvent = OrderEvent::find($event->orderEventId);

        if (! $orderEvent) {
            return;
        }

        dispatch(new ProcessShopifyOrderEvent($orderEvent));
    }
}
