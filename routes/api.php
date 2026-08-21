<?php

use App\Http\Controllers\Agent\AgentApiController;
use App\Http\Controllers\Ctwa\CtwaTrackingController;
use App\Http\Controllers\Widget\WidgetApiController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public-facing API (widget, CTWA tracking, agent)
|--------------------------------------------------------------------------
*/

// Widget bootstrap + tracking (no session auth; store identified via shop).
Route::prefix('widget')->name('widget.')->group(function () {
    Route::get('config', [WidgetApiController::class, 'config'])->name('config');
    Route::post('session', [WidgetApiController::class, 'startSession'])->name('session');
    Route::post('view', [WidgetApiController::class, 'trackView'])->name('track-view');
    Route::post('cart', [WidgetApiController::class, 'trackCart'])->name('track-cart');
    Route::post('optin', [WidgetApiController::class, 'captureOptin'])->name('optin');
    Route::post('popup', [WidgetApiController::class, 'recordPopup'])->name('popup');
});

// CTWA ad click redirect + conversion API.
Route::get('c/wa/{ad}', [CtwaTrackingController::class, 'click'])->name('ctwa.click');
Route::post('c/wa/{ad}/lead', [CtwaTrackingController::class, 'lead'])->name('ctwa.lead');

// Public conversational agent endpoint used by the chat widget.
Route::post('agent/message', [AgentApiController::class, 'message'])->name('agent.message');

/*
|--------------------------------------------------------------------------
| Authenticated store API (dashboard AJAX over the store guard)
|--------------------------------------------------------------------------
*/
// shopify.session lets the embedded admin iframe authenticate via the id_token
// query param (cookies are blocked inside the admin iframe).
Route::middleware(['shopify.session', 'auth.store', 'store.enabled'])->prefix('api')->name('api.')->group(function () {
    Route::get('conversations', [\App\Http\Controllers\Inbox\InboxApiController::class, 'list'])->name('conversations.list');
    Route::get('conversations/{conversation}/messages', [\App\Http\Controllers\Inbox\InboxApiController::class, 'messages'])->name('conversations.messages');
});
