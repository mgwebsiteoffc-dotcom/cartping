<?php

namespace App\Providers;

use App\Events\ContactOptIn;
use App\Events\CtwaClickRecorded;
use App\Events\OrderEventReceived;
use App\Events\WhatsAppMessageReceived;
use App\Listeners\DispatchShopifyEventJob;
use App\Listeners\HandleContactOptIn;
use App\Listeners\RecordCtwaClick;
use App\Listeners\RouteInboundWhatsapp;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        OrderEventReceived::class => [DispatchShopifyEventJob::class],
        WhatsAppMessageReceived::class => [RouteInboundWhatsapp::class],
        ContactOptIn::class => [HandleContactOptIn::class],
        CtwaClickRecorded::class => [RecordCtwaClick::class],
    ];
}
