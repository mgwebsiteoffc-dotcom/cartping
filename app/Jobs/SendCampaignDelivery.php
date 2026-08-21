<?php

namespace App\Jobs;

use App\Models\CampaignDelivery;
use App\Models\AnalyticsEvent;
use App\Services\Whatsapp\WhatsappSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendCampaignDelivery implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public CampaignDelivery $delivery)
    {
        $this->onQueue('default');
    }

    public function handle(WhatsappSender $sender): void
    {
        if ($this->delivery->status !== 'pending') {
            return;
        }

        $campaign = $this->delivery->campaign;
        $contact = $this->delivery->contact;
        $store = $campaign->store;

        if (! $campaign || ! $contact || ! $contact->hasOptedIn()) {
            $this->delivery->update(['status' => 'skipped']);
            return;
        }

        try {
            $template = $campaign->template;

            if ($template && $template->isApproved()) {
                $sender->template(
                    $store,
                    $contact,
                    $template->name,
                    $template->language ?: 'en',
                    [],
                    template: $template
                );
            } elseif ($campaign->message_body) {
                $sender->text($store, $contact, $campaign->message_body, template: $template);
            } else {
                $this->delivery->update(['status' => 'skipped', 'error' => 'No template or message body.']);
                return;
            }

            $this->delivery->update(['status' => 'sent', 'sent_at' => now()]);
            $campaign->increment('sent_count');

            AnalyticsEvent::record('campaign.sent', [
                'store_id' => $store->id,
                'contact_id' => $contact->id,
                'template_id' => $template?->id,
                'payload' => ['campaign_id' => $campaign->id],
            ]);
        } catch (\Throwable $e) {
            $this->delivery->update(['status' => 'failed', 'error' => $e->getMessage()]);
            $campaign->increment('failed_count');
            $this->release(30);
        }
    }
}
