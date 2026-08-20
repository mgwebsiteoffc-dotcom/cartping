<?php

namespace App\Jobs;

use App\Models\Store;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends a Conversion API (CAPI) event to Meta for ROAS measurement.
 * Uses the pixel id + access token; if the store hasn't configured CAPI we
 * gracefully skip (no hard failure) and log for setup.
 */
class SendMetaConversionEvent implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(
        public Store $store,
        public string $eventName,
        public array $data = [],
    ) {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $pixelId = config('services.meta.pixel_id');
        $accessToken = $this->store->settings['meta_access_token']
            ?? config('services.meta.access_token');

        if (! $pixelId || ! $accessToken) {
            Log::channel('whatsapp')->debug('Meta CAPI not configured; skipping event', [
                'store' => $this->store->id,
                'event' => $this->eventName,
            ]);
            return;
        }

        $response = Http::post("https://graph.facebook.com/".config('services.meta.graph_version')."/{$pixelId}/events", [
            'access_token' => $accessToken,
            'data' => [[
                'event_name' => $this->eventName,
                'event_time' => time(),
                'action_source' => 'website',
                'event_source_url' => 'https://'.$this->store->myshopify_domain,
                'user_data' => $this->data['user_data'] ?? [],
                'custom_data' => $this->data['custom_data'] ?? [],
                'value' => $this->data['value'] ?? null,
                'currency' => $this->data['currency'] ?? $this->store->currency,
            ]],
        ]);

        if ($response->failed()) {
            Log::channel('whatsapp')->error('Meta CAPI send failed', [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            $this->release(60);
        }
    }
}
