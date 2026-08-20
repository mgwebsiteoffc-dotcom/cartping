<?php

namespace App\Jobs;

use App\Models\OrderEvent;
use App\Services\Automation\AutomationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Handles a persisted Shopify order event: marks it processed and feeds it
 * into the automation engine.
 */
class ProcessShopifyOrderEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public OrderEvent $orderEvent)
    {
        $this->onQueue('webhooks');
    }

    public function handle(AutomationService $automations): void
    {
        if ($this->orderEvent->processed_at) {
            return; // idempotent
        }

        $automations->onOrderEvent($this->orderEvent->store, $this->orderEvent->topic, $this->orderEvent->payload);

        $this->orderEvent->update([
            'processed_at' => now(),
            'processed_by_job' => static::class,
        ]);
    }
}
