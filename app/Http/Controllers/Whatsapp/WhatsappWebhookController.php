<?php

namespace App\Http\Controllers\Whatsapp;

use App\Data\WhatsappEvent;
use App\Events\WhatsAppMessageReceived;
use App\Http\Controllers\Controller;
use App\Services\Whatsapp\WhatsappManager;
use Illuminate\Http\Request;

/**
 * Inbound webhook endpoint shared by all providers (/webhooks/whatsapp/{provider}).
 * Verification (GET) and signature checks are handled by the middleware; here
 * we normalize and dispatch events.
 */
class WhatsappWebhookController extends Controller
{
    public function __construct(protected WhatsappManager $whatsapp)
    {
    }

    public function verify(Request $request, string $provider)
    {
        // Middleware already validated and returned the challenge on success;
        // this branch is reached only for GET when verification succeeded.
        return response()->json(['success' => true]);
    }

    public function handle(Request $request, string $provider)
    {
        $store = $request->attributes->get('whatsapp_store');
        $connection = $request->attributes->get('whatsapp_connection');

        $events = $this->whatsapp->forConnection($connection)->normalizeInbound($request->json()->all());

        foreach ($events as $event) {
            WhatsAppMessageReceived::dispatch($store, $event);
        }

        // Always ack 200 quickly so providers don't retry.
        return response()->json(['received' => count($events)]);
    }
}
