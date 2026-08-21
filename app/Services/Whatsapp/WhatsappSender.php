<?php

namespace App\Services\Whatsapp;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Store;
use App\Models\Template;
use App\Models\AnalyticsEvent;
use Illuminate\Support\Facades\Log;

/**
 * High-level outbound sender: takes a store + recipient + content, sends via
 * the resolved provider, persists the Message row and records analytics.
 * This is the only place the app talks to the provider for outbound messages.
 */
class WhatsappSender
{
    public function __construct(protected WhatsappManager $whatsapp)
    {
    }

    public function text(
        Store $store,
        Contact $contact,
        string $body,
        ?Conversation $conversation = null,
        ?string $replyTo = null,
        ?Template $template = null,
    ): array {
        $this->guardPlanLimit($store);

        $provider = $this->whatsapp->for($store);

        $result = $provider->sendText($contact->wa_id, $body, $replyTo);

        $message = $this->persist($store, $contact, $conversation, 'outbound', 'text', $body, $result, $template);

        AnalyticsEvent::record('message.sent', [
            'store_id' => $store->id,
            'contact_id' => $contact->id,
            'conversation_id' => $conversation?->id,
            'template_id' => $template?->id,
            'value' => null,
        ]);

        return ['result' => $result, 'message' => $message];
    }

    public function template(
        Store $store,
        Contact $contact,
        string $templateName,
        string $lang,
        array $components = [],
        ?Conversation $conversation = null,
        ?Template $template = null,
    ): array {
        $this->guardPlanLimit($store);

        $provider = $this->whatsapp->for($store);

        $result = $provider->sendTemplate($contact->wa_id, $templateName, $lang, $components);

        $body = $this->bodyFromComponents($components);
        $this->persist($store, $contact, $conversation, 'outbound', 'template', $body, $result, $template);

        AnalyticsEvent::record('template.sent', [
            'store_id' => $store->id,
            'contact_id' => $contact->id,
            'conversation_id' => $conversation?->id,
            'template_id' => $template?->id,
        ]);

        return ['result' => $result];
    }

    /**
     * Enforce the store's plan message limit before sending an outbound message.
     */
    protected function guardPlanLimit(Store $store): void
    {
        if (! $store->canSendMessage()) {
            throw new \App\Exceptions\PlanLimitExceededException::messages(
                $store->planMessageLimit(),
                $store->messagesUsedThisMonth()
            );
        }
    }

    protected function persist(
        Store $store,
        Contact $contact,
        ?Conversation $conversation,
        string $direction,
        string $kind,
        ?string $body,
        array $providerResult,
        ?Template $template,
    ): Message {
        $conversation ??= $this->ensureConversation($store, $contact);

        $providerMessageId = data_get($providerResult, 'messages.0.id')
            ?? data_get($providerResult, 'message_id');

        $conversation->update(['last_message_at' => now()]);

        return Message::create([
            'store_id' => $store->id,
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => $direction,
            'role' => $direction === 'outbound' ? 'agent' : 'contact',
            'kind' => $kind,
            'body' => $body,
            'provider_message_id' => $providerMessageId,
            'template_id' => $template?->id,
            'status' => 'sent',
            'sent_at' => now(),
            'metadata' => ['provider_result' => $providerResult],
        ]);
    }

    protected function ensureConversation(Store $store, Contact $contact): Conversation
    {
        $conversation = Conversation::query()
            ->where('store_id', $store->id)
            ->where('contact_id', $contact->id)
            ->whereNotIn('status', ['resolved', 'closed'])
            ->latest('last_message_at')
            ->first();

        if ($conversation) {
            return $conversation;
        }

        return Conversation::create([
            'store_id' => $store->id,
            'contact_id' => $contact->id,
            'status' => 'open',
            'agent_mode' => 'auto',
            'source' => 'template',
        ]);
    }

    protected function bodyFromComponents(array $components): ?string
    {
        foreach ($components as $c) {
            if (($c['type'] ?? null) === 'body') {
                $body = $c['body'][0]['text'] ?? null;
                if ($body) {
                    return $body;
                }
            }
        }

        return null;
    }
}
