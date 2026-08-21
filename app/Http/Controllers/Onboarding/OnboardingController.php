<?php

namespace App\Http\Controllers\Onboarding;

use App\Http\Controllers\Controller;
use App\Models\AgentConfig;
use App\Models\Store;
use App\Models\Template;
use App\Models\WhatsappConnection;
use App\Models\WidgetConfig;
use App\Services\Shopify\ShopifySyncService;
use App\Services\Templates\AiTemplateGenerator;
use App\Services\Templates\TemplateService;
use App\Services\Whatsapp\WhatsappManager;
use App\Services\Whatsapp\WhatsappSender;
use Illuminate\Http\Request;

/**
 * Merchant-friendly onboarding wizard. Steps:
 *  1. Shopify connection (OAuth or manual)
 *  2. WhatsApp provider setup + test
 *  3. AI agent configuration
 *  4. Widget installation
 *  5. First template creation
 *  6. Test message -> complete
 */
class OnboardingController extends Controller
{
    public function index()
    {
        $store = request()->user('store');

        return view('onboarding.index', [
            'store' => $store,
            'step' => $store->onboarding_step,
        ]);
    }

    public function connectShopify(Request $request, ShopifySyncService $syncService)
    {
        $store = request()->user('store');

        $data = $request->validate(['shopify_access_token' => ['nullable', 'string']]);

        // Manual token mode.
        if (! empty($data['shopify_access_token'])) {
            $store->shopifyConnection()->updateOrCreate(
                ['store_id' => $store->id],
                ['mode' => 'manual', 'access_token' => $data['shopify_access_token'], 'shop' => $store->myshopify_domain]
            );
        }

        // Sync synchronously so products appear immediately even without a queue worker.
        $syncService->sync($store);

        $store->advanceOnboardingTo(2);

        return redirect()->route('onboarding.index');
    }

    public function connectWhatsapp(Request $request, WhatsappManager $whatsapp)
    {
        $store = request()->user('store');

        $data = $request->validate([
            'provider' => ['required', 'in:meta,whatify'],
            // Meta uses a bearer token; Whatify uses an API key (+ secret).
            'token' => ['nullable', 'string'],
            'api_key' => ['nullable', 'string'],
            'api_secret' => ['nullable', 'string'],
            'phone_number_id' => ['nullable', 'string'],
            'waba_id' => ['nullable', 'string'],
            'base_url' => ['nullable', 'url'],
        ]);

        $isWhatify = $data['provider'] === WhatsappConnection::PROVIDER_WHATIFY;

        // Provider-specific required credential.
        $credential = $isWhatify ? ($data['api_key'] ?? '') : ($data['token'] ?? '');
        if (empty($credential)) {
            return back()->withErrors(['whatsapp' => $isWhatify
                ? 'Please enter your Whatify API key.'
                : 'Please enter your Meta access token.']);
        }

        $connection = WhatsappConnection::updateOrCreate(
            ['store_id' => $store->id],
            [
                'provider' => $data['provider'],
                'token' => $data['token'],
                'api_key' => $data['api_key'],
                'api_secret' => $data['api_secret'],
                'phone_number_id' => $data['phone_number_id'],
                'waba_id' => $data['waba_id'],
                // Always pin the live Whatify endpoint.
                'base_url' => $isWhatify ? 'https://whatify.in/api/v1/external' : ($data['base_url'] ?? null),
                'is_connected' => false,
            ]
        );

        $ping = $whatsapp->forConnection($connection)->ping();
        $connection->update(['is_connected' => $ping['ok'] ?? false, 'connected_at' => now()]);

        if (! ($ping['ok'] ?? false)) {
            return back()->withErrors(['whatsapp' => 'Could not connect to the provider. Check credentials.']);
        }

        $store->advanceOnboardingTo(3);

        return redirect()->route('onboarding.index');
    }

    public function testWhatsapp(Request $request, WhatsappManager $whatsapp)
    {
        $store = request()->user('store');

        $data = $request->validate(['test_number' => ['required', 'regex:/^\+?[0-9]{7,15}$/']]);

        $contact = \App\Models\Contact::firstOrCreate(
            ['store_id' => $store->id, 'wa_id' => preg_replace('/\D+/', '', $data['test_number'])],
            ['profile_name' => 'Test']
        );

        try {
            $whatsapp->for($store)->sendText($contact->wa_id, "🎉 You're connected to {$store->name} on CartPing!");
            return back()->with('status', 'Test message sent successfully.');
        } catch (\Throwable $e) {
            return back()->withErrors(['test' => 'Test send failed: '.$e->getMessage()]);
        }
    }

    public function configureAgent(Request $request)
    {
        $store = request()->user('store');

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'persona' => ['nullable', 'string'],
            'enabled_tools' => ['nullable', 'array'],
            'autonomous' => ['boolean'],
        ]);

        AgentConfig::updateOrCreate(
            ['store_id' => $store->id],
            [
                'name' => $data['name'] ?? 'Store Assistant',
                'persona' => $data['persona'] ?? null,
                'enabled_tools' => $data['enabled_tools'] ?? null,
                'enabled' => true,
                'autonomous' => $request->boolean('autonomous', true),
                'rag_enabled' => true,
            ]
        );

        $store->advanceOnboardingTo(4);

        return redirect()->route('onboarding.index');
    }

    public function installWidget(Request $request)
    {
        $store = request()->user('store');

        $data = $request->validate([
            'type' => ['nullable', 'in:simple_button,tooltip,chat_widget,smart_contextual'],
        ]);

        WidgetConfig::updateOrCreate(
            ['store_id' => $store->id],
            [
                'type' => $data['type'] ?? 'chat_widget',
                'enabled' => true,
                'install_mode' => 'manual',
            ]
        );

        $store->advanceOnboardingTo(5);

        return redirect()->route('onboarding.index');
    }

    public function createFirstTemplate(Request $request, TemplateService $templates)
    {
        $store = request()->user('store');

        $data = $request->validate([
            'brief' => ['required', 'string', 'max:2000'],
        ]);

        $templates->createFromAi($store, $data['brief']);

        $store->advanceOnboardingTo(6);

        return redirect()->route('onboarding.complete');
    }

    public function sendTestMessage(Request $request, WhatsappSender $sender)
    {
        $store = request()->user('store');

        $data = $request->validate(['test_number' => ['required', 'regex:/^\+?[0-9]{7,15}$/']]);

        $contact = \App\Models\Contact::firstOrCreate(
            ['store_id' => $store->id, 'wa_id' => preg_replace('/\D+/', '', $data['test_number'])],
            ['profile_name' => 'Test']
        );

        $sender->text($store, $contact, "Hello {$store->name}! Your WhatsApp automation is live. 🚀");

        return redirect()->route('onboarding.complete');
    }

    public function complete()
    {
        $store = request()->user('store');
        $store->advanceOnboardingTo(6);

        return view('onboarding.complete', ['store' => $store]);
    }
}
