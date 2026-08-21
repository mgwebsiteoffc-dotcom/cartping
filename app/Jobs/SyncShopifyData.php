<?php

namespace App\Jobs;

use App\Models\Store;
use App\Services\Shopify\ShopifySyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Queued (async) Shopify sync — used when a worker is available. For immediate
 * results without a worker (e.g. shared hosting), call ShopifySyncService
 * directly instead.
 */
class SyncShopifyData implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(public Store $store)
    {
        $this->onQueue('low');
    }

    public function handle(ShopifySyncService $service): void
    {
        $service->sync($this->store);
    }
}
