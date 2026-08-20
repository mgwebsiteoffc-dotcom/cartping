<?php

namespace App\Jobs;

use App\Models\AutomationRun;
use App\Models\AnalyticsEvent;
use App\Models\Template;
use App\Services\Whatsapp\WhatsappManager;
use App\Services\Whatsapp\WhatsappSender;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAutomationMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $backoff = 60;

    public function __construct(public AutomationRun $run)
    {
    }

    public function handle(WhatsappSender $sender, WhatsappManager $manager): void
    {
        if ($this->run->state !== 'scheduled') {
            return; // already processed
        }

        $store = $this->run->store;
        $contact = $this->run->contact;
        $automation = $this->run->automation;

        if (! $contact || ! $store->whatsappConnection?->is_connected) {
            $this->run->update(['state' => 'skipped']);
            return;
        }

        // Enforce opt-in for marketing automations (service messages exempt).
        if ($automation->trigger !== 'order_created' && ! $contact->hasOptedIn()) {
            $this->run->update(['state' => 'skipped', 'error' => 'no_marketing_consent']);
            return;
        }

        $template = $automation->template;

        try {
            if ($template && $template->isApproved()) {
                $body = $this->renderTemplateBody($template, $this->run);
                $result = $sender->text($store, $contact, $body, conversation: null, template: $template);
            } elseif ($template) {
                // Fall back to plain text if the template isn't approved yet.
                $body = $this->renderTemplateBody($template, $this->run);
                $result = $sender->text($store, $contact, $body);
            } else {
                $result = $sender->text($store, $contact, $automation->agent_flow
                    ? 'A store assistant will help you with this.'
                    : 'Thank you for shopping with '.$store->name.'!');
            }

            $this->run->update([
                'state' => 'sent',
                'attempted_at' => now(),
                'provider_message_id' => data_get($result, 'result.messages.0.id'),
            ]);

            $automation->increment('send_count');

            AnalyticsEvent::record('automation.converted_sent', [
                'store_id' => $store->id,
                'contact_id' => $contact->id,
                'automation_id' => $automation->id,
                'template_id' => $template?->id,
            ]);
        } catch (\Throwable $e) {
            $this->run->update(['state' => 'failed', 'error' => $e->getMessage()]);
            $this->release($this->backoff);
        }
    }

    protected function renderTemplateBody(Template $template, AutomationRun $run): string
    {
        $body = $template->body;
        $order = $run->order;

        $replacements = [
            '{{1}}' => $run->contact?->profile_name ?: 'there',
            '{{2}}' => '#'.$order?->order_number,
            '{{3}}' => $order?->tracking_url,
        ];

        return strtr($body, $replacements);
    }
}
