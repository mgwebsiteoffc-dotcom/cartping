<?php

namespace App\Events;

use App\Data\WhatsappEvent;
use App\Models\Store;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A normalized inbound WhatsApp message was received from a provider webhook.
 * The listener routes it into the inbox + AI agent pipeline on the high queue.
 */
class WhatsAppMessageReceived
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Store $store,
        public WhatsappEvent $event,
    ) {
    }
}
