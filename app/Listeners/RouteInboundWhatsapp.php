<?php

namespace App\Listeners;

use App\Events\WhatsAppMessageReceived;
use App\Jobs\ProcessInboundWhatsapp;

class RouteInboundWhatsapp
{
    public function handle(WhatsAppMessageReceived $event): void
    {
        dispatch(new ProcessInboundWhatsapp($event->store, $event->event));
    }
}
