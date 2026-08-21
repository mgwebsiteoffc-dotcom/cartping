<?php

namespace App\Http\Controllers\Flow;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Store;
use App\Services\Whatsapp\WhatsappSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Shopify Flow runtime endpoints.
 *
 * When a merchant builds a Flow in Shopify using CartPing's custom triggers /
 * actions, Shopify POSTs the configured runtimeUrl with a signed payload.
 * Triggers fire when something happens in CartPing (inbound message, CTWA lead,
 * opt-in); actions are invoked by the Flow at run time (e.g. send a template).
 *
 * Note: Shopify signs these requests with a session JWT (Authorization Bearer).
 * This controller resolves the store from the payload and processes the action.
 */
class FlowWebhookController extends Controller
{
    public function triggerMessageReceived(Request $request, WhatsappSender $sender)
    {
        $payload = $request->json()->all();

        // Flow runtime payload includes the shop + the trigger config + data.
        $shop = $this->resolveShop($payload);
        $store = $shop ? Store::where('myshopify_domain', $shop)->first() : null;

        if (! $store) {
            Log::channel('whatsapp')->warning('Flow trigger: unknown store', ['payload' => $payload]);
            return response()->json(['error' => 'Unknown store'], 404);
        }

        // This trigger is informational (a message arrived). Optionally start a
        // welcome flow or reply. For now, acknowledge so the Flow continues.
        return response()->json(['success' => true, 'shop' => $store->myshopify_domain]);
    }

    /**
     * Action: send a WhatsApp template to a customer phone.
     * Expects payload: { shop, template_name, customer_phone }
     */
    public function actionSendTemplate(Request $request, WhatsappSender $sender)
    {
        $payload = $request->json()->all();

        $shop = $this->resolveShop($payload);
        $store = $shop ? Store::where('myshopify_domain', $shop)->first() : null;

        if (! $store) {
            return response()->json(['error' => 'Unknown store'], 404);
        }

        $templateName = $payload['template_name'] ?? null;
        $phone = preg_replace('/\D+/', '', (string) ($payload['customer_phone'] ?? ''));

        if (! $templateName || ! $phone) {
            return response()->json(['error' => 'template_name and customer_phone are required'], 422);
        }

        try {
            $contact = Contact::firstOrCreate(
                ['store_id' => $store->id, 'wa_id' => $phone],
                ['profile_name' => 'Flow']
            );

            $sender->template($store, $contact, $templateName, 'en', []);

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('Flow action send-template failed', ['error' => $e->getMessage()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    protected function resolveShop(array $payload): ?string
    {
        $shop = $payload['shop']
            ?? $payload['shop_domain']
            ?? ($payload['data']['shop'] ?? null);

        if (! $shop) {
            return null;
        }

        $shop = strtolower(trim($shop));
        if (preg_match('#^https?://#', $shop)) {
            $shop = parse_url($shop, PHP_URL_HOST) ?: $shop;
        }

        return $shop;
    }
}
