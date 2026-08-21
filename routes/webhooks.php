<?php

use App\Http\Controllers\Flow\FlowWebhookController;
use App\Http\Controllers\Shopify\ShopifyWebhookController;
use App\Http\Controllers\Whatsapp\WhatsappWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Inbound webhooks (Shopify + WhatsApp providers + Shopify Flow)
|--------------------------------------------------------------------------
*/

// Shopify events -> /webhooks/shopify/{topic}
Route::prefix('webhooks/shopify')->name('shopify.webhook.')->group(function () {
    Route::post('/{topic}', [ShopifyWebhookController::class, 'handle'])
        ->middleware('shopify.webhook')
        ->name('handle');
});

// WhatsApp provider events -> /webhooks/whatsapp/{provider}
Route::prefix('webhooks/whatsapp')->name('whatsapp.webhook.')->group(function () {
    Route::get('/{provider}', [WhatsappWebhookController::class, 'verify'])->middleware('whatsapp.webhook');
    Route::post('/{provider}', [WhatsappWebhookController::class, 'handle'])->middleware('whatsapp.webhook');
});

// Shopify Flow runtime endpoints (invoked by Flow when triggers fire / actions run).
Route::prefix('webhooks/flow')->name('flow.webhook.')->group(function () {
    Route::post('/trigger/message-received', [FlowWebhookController::class, 'triggerMessageReceived'])->name('trigger.message-received');
    Route::post('/action/send-template', [FlowWebhookController::class, 'actionSendTemplate'])->name('action.send-template');
});
