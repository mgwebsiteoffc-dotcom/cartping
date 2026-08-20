<?php

use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\ShopifyAuthController;
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
| Public / marketing
|--------------------------------------------------------------------------
*/
Route::get('/', function () {
    // Authenticated merchants (installed via OAuth) go straight into the app —
    // not back to the marketing landing page. Incomplete setup → onboarding.
    $store = request()->user('store');

    if ($store) {
        return $store->onboarding_complete
            ? redirect()->route('dashboard.index')
            : redirect()->route('onboarding.index');
    }

    return view('marketing.landing');
})->name('home');

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

    // Mode 2: manual custom-app token signup (choose provider + paste token).
    Route::get('manual', [AuthController::class, 'showManual'])->name('manual');
    Route::post('manual', [AuthController::class, 'manualConnect']);
});

/*
|--------------------------------------------------------------------------
| Merchant dashboard (authenticated store)
|--------------------------------------------------------------------------
*/
Route::middleware('auth.store')->group(function () {
    Route::get('/dashboard', [ShopifyController::class, 'dashboard'])->name('dashboard.index');

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

    Route::get('/settings/shopify', [ShopifyController::class, 'settings'])->name('settings.shopify');
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
        Route::post('/templates/generate', [TemplateController::class, 'generate'])->name('templates.generate');
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
