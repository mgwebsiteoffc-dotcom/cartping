<?php

namespace App\Listeners;

use App\Events\ContactOptIn;
use App\Models\AnalyticsEvent;
use App\Services\Automation\AutomationService;

class HandleContactOptIn
{
    public function handle(ContactOptIn $event, AutomationService $automations): void
    {
        AnalyticsEvent::record('consent.opt_in', [
            'store_id' => $event->store->id,
            'contact_id' => $event->contact->id,
            'payload' => ['source' => $event->source],
        ]);

        $automations->onWelcome($event->store, $event->contact, $event->source);
    }
}
