<?php

namespace App\Events;

use App\Models\Contact;
use App\Models\Store;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * A contact granted WhatsApp marketing consent (GDPR opt-in). Audited so we
 * can prove consent and trigger a welcome automation.
 */
class ContactOptIn
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Store $store,
        public Contact $contact,
        public string $source,
    ) {
    }
}
