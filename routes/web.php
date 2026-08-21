<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ShopifyAuthController;
use App\Http\Controllers\Platform\OwnerController;
use App\Http\Controllers\Platform\PlatformAuthController;
use App\Http\Controllers\Analytics\AnalyticsController;
use App\Http\Controllers\Agent\AgentController;
use App\Http\Controllers\Ctwa\CtwaController;
use App\Http\Controllers\Inbox\InboxController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Shopify\ShopifyController;
use App\Http\Controllers\Template\TemplateController;
use App\Http\Controllers\Whatsapp\WhatsappController;
use App\Http\Controllers\Widget\WidgetConfigController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Marketing site
|--------------------------------------------------------------------------
*/
// Pricing lives on the marketing site, not in the app.
Route::view('/pricing', 'marketing.pricing')->name('marketing.pricing');

/*
|--------------------------------------------------------------------------
| SaaS owner panel (platform staff only)
|--------------------------------------------------------------------------
*/
Route::prefix('owner')->name('owner.')->group(function () {
    Route::get('login', [PlatformAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [PlatformAuthController::class, 'login']);
    Route::post('logout', [PlatformAuthController::class, 'logout'])->name('logout');

    Route::middleware('owner')->group(function () {
        Route::get('/', [OwnerController::class, 'dashboard'])->name('dashboard');
        Route::get('/stores', [OwnerController::class, 'stores'])->name('stores');
        Route::post('/stores/{store}', [OwnerController::class, 'updateStore'])->name('stores.update');
        Route::post('/stores/{store}/toggle', [OwnerController::class, 'toggleStore'])->name('stores.toggle');
        Route::post('/stores/{store}/verify-charge', [OwnerController::class, 'verifyCharge'])->name('stores.verify-charge');
        Route::get('/plans', [OwnerController::class, 'plans'])->name('plans');
        Route::post('/plans', [OwnerController::class, 'storePlan'])->name('plans.store');
        Route::post('/plans/{plan}', [OwnerController::class, 'updatePlan'])->name('plans.update');
        Route::get('/users', [OwnerController::class, 'users'])->name('users');
        Route::post('/users', [OwnerController::class, 'storeUser'])->name('users.store');
    });
});

Route::get('/', function () {
    // Embedded admin loads (with a session token) must land in the app, not the
    // marketing page. Auth is handled by the shopify.session middleware below.
    $store = request()->user('store');

    if ($store) {
        // Preserve the session-token/host/shop query params through the redirect —
        // they're required to authenticate the next page load inside the admin
        // iframe (cookies are blocked there).
        $query = array_filter(request()->only(['id_token', 'session', 'host', 'shop']));

        return redirect()->route('dashboard.index', $query);
    }

    return view('marketing.landing');
})->name('home')->middleware('shopify.session');

/*
|--------------------------------------------------------------------------
| Merchant authentication (two modes)
|--------------------------------------------------------------------------
*/
Route::prefix('auth')->name('auth.')->group(function () {
    Route::get('signin', [AuthController::class, 'showSignin'])->name('signin');
    Route::post('signin', [AuthController::class, 'signin']);
    Route::get('signup', [AuthController::class, 'showSignup'])->name('signup');
    Route::post('signup', [AuthController::class, 'signup']);
    Route::post('logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth.store');

    // Mode 1: public Shopify App OAuth flow.
    Route::get('shopify', [ShopifyAuthController::class, 'redirect'])->name('shopify');
    Route::get('shopify/callback', [ShopifyAuthController::class, 'callback'])->name('shopify.callback');
    // Embedded session-token exchange (no cookies needed in the admin iframe).
    Route::post('shopify/session', [ShopifyAuthController::class, 'session'])->name('shopify.session');

    // Mode 2: manual custom-app token signup (choose provider + paste token).
    Route::get('manual', [AuthController::class, 'showManual'])->name('manual');
    Route::post('manual', [AuthController::class, 'manualConnect']);
});

// Compatibility alias: allow the OAuth callback to be whitelisted at either
// {app}/auth/shopify/callback (canonical) OR {app}/shopify/callback.
Route::get('/shopify/callback', [ShopifyAuthController::class, 'callback'])->name('shopify.callback.legacy');

/*
|--------------------------------------------------------------------------
| Merchant dashboard (authenticated store)
|--------------------------------------------------------------------------
*/
// Shopify Billing API (subscription plans). Callback is hit by Shopify so it is
// outside the auth group; it resolves the store via the ?shop param.
Route::get('/billing/{plan}/callback', [\App\Http\Controllers\Billing\BillingController::class, 'callback'])->name('billing.callback');

Route::middleware(['shopify.session', 'auth.store', 'store.enabled'])->group(function () {
    Route::get('/dashboard', [ShopifyController::class, 'dashboard'])->name('dashboard.index');

    // Start a paid subscription (redirects to Shopify confirmation).
    Route::get('/billing/{plan}', [\App\Http\Controllers\Billing\BillingController::class, 'subscribe'])->name('billing.subscribe');


    Route::prefix('onboarding')->name('onboarding.')->group(function () {
        Route::get('/', [OnboardingController::class, 'index'])->name('index');
        Route::post('shopify', [OnboardingController::class, 'connectShopify'])->name('shopify');
        Route::post('whatsapp', [OnboardingController::class, 'connectWhatsapp'])->name('whatsapp');
        Route::post('whatsapp/test', [OnboardingController::class, 'testWhatsapp'])->name('whatsapp.test');
        Route::post('agent', [OnboardingController::class, 'configureAgent'])->name('agent');
        Route::post('widget', [OnboardingController::class, 'installWidget'])->name('widget');
        Route::post('template', [OnboardingController::class, 'createFirstTemplate'])->name('template');
        Route::post('test-message', [OnboardingController::class, 'sendTestMessage'])->name('test-message');
        Route::get('complete', [OnboardingController::class, 'complete'])->name('complete');
    });

        Route::get('/products', [\App\Http\Controllers\Shopify\ProductController::class, 'index'])->name('products.index');
        Route::post('/products/sync', [\App\Http\Controllers\Shopify\ProductController::class, 'sync'])->name('products.sync');

        // Visual chat flow builder
        Route::get('/flows', [\App\Http\Controllers\Flow\FlowController::class, 'index'])->name('flows.index');
        Route::post('/flows', [\App\Http\Controllers\Flow\FlowController::class, 'create'])->name('flows.create');
        Route::get('/flows/{flow}/builder', [\App\Http\Controllers\Flow\FlowController::class, 'builder'])->name('flows.builder');
        Route::post('/flows/{flow}/save', [\App\Http\Controllers\Flow\FlowController::class, 'save'])->name('flows.save');
        Route::post('/flows/{flow}/test-run', [\App\Http\Controllers\Flow\FlowController::class, 'testRun'])->name('flows.test-run');
        Route::get('/flows/{flow}/runs', [\App\Http\Controllers\Flow\FlowController::class, 'runs'])->name('flows.runs');

        // Broadcast campaigns
        Route::get('/campaigns', [\App\Http\Controllers\Campaign\CampaignController::class, 'index'])->name('campaigns.index');
        Route::get('/campaigns/calendar', [\App\Http\Controllers\Campaign\CampaignController::class, 'calendar'])->name('campaigns.calendar');
        Route::post('/campaigns', [\App\Http\Controllers\Campaign\CampaignController::class, 'store'])->name('campaigns.store');
        Route::get('/campaigns/{campaign}', [\App\Http\Controllers\Campaign\CampaignController::class, 'show'])->name('campaigns.show');
        Route::post('/campaigns/{campaign}/cancel', [\App\Http\Controllers\Campaign\CampaignController::class, 'destroy'])->name('campaigns.cancel');

        // Contacts & segments
        Route::get('/contacts', [\App\Http\Controllers\Contact\ContactController::class, 'index'])->name('contacts.index');
        Route::get('/contacts/export', [\App\Http\Controllers\Contact\ContactController::class, 'export'])->name('contacts.export');
        Route::post('/contacts/import', [\App\Http\Controllers\Contact\ContactController::class, 'import'])->name('contacts.import');
        Route::get('/contacts/{contact}', [\App\Http\Controllers\Contact\ContactController::class, 'show'])->name('contacts.show');
        Route::post('/contacts/{contact}/tags', [\App\Http\Controllers\Contact\ContactController::class, 'updateTags'])->name('contacts.tags');

        // Contact segments
        Route::get('/segments', [\App\Http\Controllers\Segment\SegmentController::class, 'index'])->name('segments.index');
        Route::post('/segments', [\App\Http\Controllers\Segment\SegmentController::class, 'store'])->name('segments.store');
        Route::post('/segments/preview', [\App\Http\Controllers\Segment\SegmentController::class, 'preview'])->name('segments.preview');
        Route::post('/segments/{segment}/refresh', [\App\Http\Controllers\Segment\SegmentController::class, 'refresh'])->name('segments.refresh');
        Route::post('/segments/{segment}/delete', [\App\Http\Controllers\Segment\SegmentController::class, 'destroy'])->name('segments.delete');

        Route::get('/settings/shopify', [ShopifyController::class, 'settings'])->name('settings.shopify');
        // Setup health / checklist (works before provisioning).
        Route::get('/health', [\App\Http\Controllers\Setup\HealthController::class, 'index'])->name('health.index');
    Route::get('/settings/whatsapp', [WhatsappController::class, 'settings'])->name('settings.whatsapp');
    Route::post('/settings/whatsapp', [WhatsappController::class, 'updateSettings'])->name('settings.whatsapp.update');

    Route::middleware('provisioned')->group(function () {
        Route::get('/inbox', [InboxController::class, 'index'])->name('inbox.index');
        Route::get('/inbox/{conversation}', [InboxController::class, 'show'])->name('inbox.show');
        Route::post('/inbox/{conversation}/takeover', [InboxController::class, 'takeover'])->name('inbox.takeover');
        Route::post('/inbox/{conversation}/return-to-ai', [InboxController::class, 'returnToAi'])->name('inbox.return-to-ai');
        Route::post('/inbox/{conversation}/assign', [InboxController::class, 'assign'])->name('inbox.assign');
        Route::post('/inbox/{conversation}/labels', [InboxController::class, 'attachLabels'])->name('inbox.labels');
        Route::post('/inbox/{conversation}/send', [InboxController::class, 'send'])->name('inbox.send');
        Route::get('/inbox/{conversation}/suggest', [InboxController::class, 'suggest'])->name('inbox.suggest');

        Route::get('/templates', [TemplateController::class, 'index'])->name('templates.index');
        Route::post('/templates/store', [TemplateController::class, 'store'])->name('templates.store');
        Route::post('/templates/generate', [TemplateController::class, 'generate'])->name('templates.generate');
        Route::post('/templates/sync', [TemplateController::class, 'sync'])->name('templates.sync');
        Route::post('/templates/{template}/header', [TemplateController::class, 'setHeader'])->name('templates.header');
        Route::post('/templates/{template}/ab', [TemplateController::class, 'createAbVariants'])->name('templates.ab');
        Route::post('/templates/{template}/submit', [TemplateController::class, 'submit'])->name('templates.submit');
        Route::post('/templates/{template}/approve', [TemplateController::class, 'approve'])->name('templates.approve');

        Route::get('/ctwa', [CtwaController::class, 'index'])->name('ctwa.index');
        Route::post('/ctwa', [CtwaController::class, 'store'])->name('ctwa.store');

        Route::get('/analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
        Route::get('/analytics/data', [AnalyticsController::class, 'data'])->name('analytics.data');

        Route::get('/agent', [AgentController::class, 'index'])->name('agent.index');
        Route::post('/agent', [AgentController::class, 'update'])->name('agent.update');

        Route::get('/widget', [WidgetConfigController::class, 'index'])->name('widget.index');
        Route::post('/widget', [WidgetConfigController::class, 'update'])->name('widget.update');
        Route::post('/widget/install', [WidgetConfigController::class, 'install'])->name('widget.install');
    });
});
