<?php

namespace App\Listeners;

use App\Events\CtwaClickRecorded;
use App\Models\AnalyticsEvent;

class RecordCtwaClick
{
    public function handle(CtwaClickRecorded $event): void
    {
        AnalyticsEvent::record('ctwa.click', [
            'store_id' => $event->store->id,
            'ad_id' => $event->click->ctwa_ad_id,
            'contact_id' => $event->click->converted ? $event->click->order_id : null,
            'value' => $event->click->order_total,
            'payload' => [
                'click_id' => $event->click->click_id,
                'converted' => $event->click->converted,
            ],
        ]);
    }
}
