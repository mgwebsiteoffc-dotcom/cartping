<?php

namespace App\Http\Controllers\Widget;

use App\Http\Controllers\Controller;
use App\Models\AnalyticsEvent;
use App\Models\Contact;
use App\Models\Store;
use App\Models\WidgetConfig;
use App\Models\WidgetSession;
use App\Services\Automation\AutomationService;
use Illuminate\Http\Request;

/**
 * Public API consumed by the widget JS (no session auth). The store is
 * identified by the shop query param and validated against the widget config.
 */
class WidgetApiController extends Controller
{
    public function config(Request $request)
    {
        $store = $this->storeFor($request);

        if (! $store) {
            return response()->json(['enabled' => false]);
        }

        $config = $store->widgetConfig ?? WidgetConfig::firstOrCreate(
            ['store_id' => $store->id],
            ['type' => config('widget.default_type'), 'enabled' => false]
        );

        return response()->json([
            'enabled' => $config->enabled,
            'type' => $config->type,
            'launcher' => $config->launcher,
            'simple_button' => $config->simple_button,
            'tooltip' => $config->tooltip,
            'chat_widget' => $config->chat_widget,
            'smart_contextual' => $config->smart_contextual,
            'entry_popup' => $config->entry_popup,
            'exit_popup' => $config->exit_popup,
            'display_rules' => $config->display_rules,
            'whatsapp_number' => $this->whatsappNumber($store),
            'shop' => $store->myshopify_domain,
            'currency' => $store->currency,
        ]);
    }

    public function startSession(Request $request)
    {
        $store = $this->storeFor($request);

        if (! $store) {
            return response()->json(['error' => 'unknown_store'], 404);
        }

        $data = $request->validate([
            'session_key' => ['required', 'string'],
            'page_type' => ['required', 'in:product,cart,order,home,collection,blog'],
            'fingerprint' => ['nullable', 'string'],
            'referrer' => ['nullable', 'string'],
            'ctwa_click_id' => ['nullable', 'string'],
        ]);

        $session = WidgetSession::updateOrCreate(
            [
                'store_id' => $store->id,
                'session_key' => $data['session_key'],
            ],
            [
                'page_type' => $data['page_type'],
                'fingerprint' => $data['fingerprint'],
                'referrer' => $data['referrer'],
                'ctwa_click_id' => $data['ctwa_click_id'],
                'landed_at' => now(),
                'last_active_at' => now(),
            ]
        );

        AnalyticsEvent::record('widget.loaded', [
            'store_id' => $store->id,
            'session_id' => $session->id,
            'payload' => ['page_type' => $data['page_type']],
        ]);

        return response()->json(['session_id' => $session->id]);
    }

    public function trackView(Request $request)
    {
        $store = $this->storeFor($request);
        $session = $this->sessionFor($request, $store);

        if (! $session) {
            return response()->json(['ok' => false]);
        }

        $productIds = $session->viewed_product_ids ?? [];
        if ($request->filled('product_id')) {
            $productIds = array_values(array_unique(array_merge($productIds, [(int) $request->input('product_id')])));
        }

        $session->update([
            'viewed_product_ids' => $productIds,
            'last_active_at' => now(),
            'page_type' => $request->input('page_type', $session->page_type),
        ]);

        return response()->json(['ok' => true, 'viewed' => $productIds]);
    }

    public function trackCart(Request $request)
    {
        $store = $this->storeFor($request);
        $session = $this->sessionFor($request, $store);

        if (! $session) {
            return response()->json(['ok' => false]);
        }

        $data = $request->validate([
            'cart' => ['required', 'array'],
            'checkout_url' => ['nullable', 'string'],
            'cart_token' => ['nullable', 'string'],
        ]);

        $session->update([
            'cart' => $data['cart'],
            'checkout_url' => $data['checkout_url'],
            'cart_token' => $data['cart_token'],
            'last_active_at' => now(),
            'page_type' => 'cart',
        ]);

        return response()->json(['ok' => true]);
    }

    /**
     * Capture a WhatsApp number (GDPR opt-in) from a popup — enables entry &
     * exit popup flows and links to abandoned browse recovery.
     */
    public function captureOptin(Request $request, AutomationService $automations)
    {
        $store = $this->storeFor($request);
        $session = $this->sessionFor($request, $store);

        $data = $request->validate([
            'wa_number' => ['required', 'regex:/^\+?[0-9]{7,15}$/'],
            'profile_name' => ['nullable', 'string'],
        ]);

        $waId = preg_replace('/\D+/', '', $data['wa_number']);

        $contact = Contact::firstOrCreate(
            ['store_id' => $store->id, 'wa_id' => $waId],
            ['profile_name' => $data['profile_name'] ?? null]
        );

        $contact->grantOptIn($session?->ctwa_click_id ? 'ctwa' : 'widget');

        if ($session) {
            $session->update([
                'contact_id' => $contact->id,
                'opted_in_wa' => true,
                'wa_number' => $waId,
            ]);
        }

        event(new \App\Events\ContactOptIn($store, $contact, 'widget'));

        return response()->json(['ok' => true, 'contact_id' => $contact->id]);
    }

    public function recordPopup(Request $request)
    {
        $store = $this->storeFor($request);
        $session = $this->sessionFor($request, $store);

        AnalyticsEvent::record('popup.shown', [
            'store_id' => $store->id,
            'session_id' => $session?->id,
            'payload' => ['popup' => $request->input('popup', 'entry')],
        ]);

        if ($request->input('popup') === 'exit') {
            $session?->update(['bounced' => true]);
        }

        return response()->json(['ok' => true]);
    }

    /* ------------------------------ Helpers ----------------------------- */

    protected function storeFor(Request $request): ?Store
    {
        $shop = $request->query('shop', $request->input('shop'));

        return $shop ? Store::where('myshopify_domain', $shop)->first() : null;
    }

    protected function sessionFor(Request $request, ?Store $store): ?WidgetSession
    {
        if (! $store) {
            return null;
        }

        return WidgetSession::where('store_id', $store->id)
            ->where('session_key', $request->input('session_key'))
            ->latest('last_active_at')
            ->first();
    }

    protected function whatsappNumber(Store $store): ?string
    {
        return $store->whatsappConnection?->phone_number_id
            ?? $store->settings['whatsapp_number']
            ?? null;
    }
}
