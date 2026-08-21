<?php

namespace App\Services\Whatsapp;

use App\Data\WhatsappEvent;
use App\Models\AnalyticsEvent;
use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\Store;
use App\Services\Ai\AgentOrchestrator;
use App\Services\Automation\FlowRunner;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

/**
 * Routes a normalized inbound WhatsApp event into the system:
 *  1. resolve/create the Contact (with opt-in handling)
 *  2. resolve/create the Conversation
 *  3. persist the inbound Message
 *  4. broadcast to the real-time inbox (Reverb)
 *  5. run any matching automation flow
 *  6. if the thread is in auto mode, run the AI agent and send its reply
 */
class InboundPipeline
{
    public function __construct(
        protected AgentOrchestrator $agent,
        protected WhatsappSender $sender,
        protected WhatsappManager $whatsapp,
        protected FlowRunner $flows,
    ) {
    }

    public function handle(Store $store, WhatsappEvent $event): void
    {
        if ($event->type === 'message_status') {
            $this->handleStatus($store, $event);
            return;
        }

        if (! $event->waId) {
            Log::channel('whatsapp')->warning('Inbound event without wa_id', ['event' => $event->type]);
            return;
        }

        $contact = $this->resolveContact($store, $event);
        $conversation = $this->resolveConversation($store, $contact, $event);

        $message = Message::create([
            'store_id' => $store->id,
            'conversation_id' => $conversation->id,
            'contact_id' => $contact->id,
            'direction' => 'inbound',
            'role' => 'contact',
            'kind' => $event->kind ?? 'text',
            'body' => $event->body,
            'media_url' => $event->mediaUrl,
            'mime_type' => $event->mediaMimeType,
            'provider_message_id' => $event->messageId,
            'status' => 'sent',
            'sent_at' => now(),
            'metadata' => ['raw' => $event->raw],
        ]);

        $conversation->update(['last_message_at' => now()]);

        $this->broadcast($conversation, $message);

        AnalyticsEvent::record('message.received', [
            'store_id' => $store->id,
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
        ]);

        // Store the last inbound body on the conversation for flow conditions.
        $conversation->update(['meta' => array_merge($conversation->meta ?? [], [
            'last_inbound_body' => $event->body,
        ])]);

        // Run any matching automation flow (welcome / new_message / keyword).
        $this->runMatchingFlow($store, $contact, $conversation, $event->body);

        // Let the AI agent reply if the thread is autonomous (and no flow is
        // currently running/controlling it).
        if ($conversation->agent_mode === 'auto') {
            $this->runAgent($store, $contact, $conversation, $event->body);
        }
    }

    /**
     * Trigger a matching active flow for this conversation.
     */
    protected function runMatchingFlow(Store $store, Contact $contact, Conversation $conversation, ?string $body): void
    {
        $isNew = $conversation->messages()->count() <= 1;

        $flow = \App\Models\Flow::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->get()
            ->first(function ($flow) use ($isNew, $body, $conversation) {
                return match ($flow->trigger) {
                    'welcome' => $isNew,
                    'new_message' => true,
                    'keyword' => $flow->trigger_value
                        && $body
                        && str_contains(mb_strtolower($body), mb_strtolower($flow->trigger_value)),
                    default => false,
                };
            });

        if ($flow) {
            // If a run is already in progress for this contact, skip to avoid loops.
            $existing = \App\Models\FlowRun::where('flow_id', $flow->id)
                ->where('contact_id', $contact->id)
                ->whereIn('state', ['running'])
                ->exists();

            if ($existing) {
                return;
            }

            $this->flows->run($store, $flow, $contact, $conversation);
        }
    }

    protected function runAgent(Store $store, Contact $contact, Conversation $conversation, ?string $userMessage): void
    {
        if (! $userMessage || ! $store->agentConfig?->enabled) {
            return;
        }

        $history = $conversation->messages()
            ->orderBy('created_at')
            ->limit(20)
            ->get()
            ->map(fn (Message $m) => [
                'role' => $m->direction === 'outbound' ? 'assistant' : 'user',
                'content' => $m->body,
            ])
            ->all();

        $response = $this->agent->respond($store, $userMessage, $contact, $conversation, $history);

        // Record AI metadata + resolution/action analytics.
        $conversation->update([
            'ai_confidence' => $response['confidence'],
            'status' => $response['escalated'] ? 'pending_human' : $conversation->status,
            'agent_mode' => $response['escalated'] ? 'human_takeover' : $conversation->agent_mode,
            'escalated_reason' => $response['escalated'] ? ($response['escalate_reason'] ?? 'auto') : $conversation->escalated_reason,
        ]);

        AnalyticsEvent::record('ai.action', [
            'store_id' => $store->id,
            'contact_id' => $contact->id,
            'conversation_id' => $conversation->id,
            'payload' => [
                'tool_calls' => $response['tool_calls'],
                'rounds' => $response['rounds'],
                'confidence' => $response['confidence'],
            ],
        ]);

        if ($response['escalated']) {
            AnalyticsEvent::record('agent.escalated', [
                'store_id' => $store->id,
                'contact_id' => $contact->id,
                'conversation_id' => $conversation->id,
                'payload' => ['reason' => $response['escalate_reason']],
            ]);
            return;
        }

        if ($response['content']) {
            $sent = $this->sender->text($store, $contact, $response['content'], $conversation);
            $this->broadcast($conversation, $sent['message']);
        }
    }

    protected function resolveContact(Store $store, WhatsappEvent $event): Contact
    {
        $contact = Contact::firstOrNew([
            'store_id' => $store->id,
            'wa_id' => $event->waId,
        ]);

        if (! $contact->exists) {
            $contact->fill([
                'profile_name' => $event->profileName,
                'first_seen_at' => now(),
                'last_seen_at' => now(),
                'consent_state' => 'NOT_REQUIRED', // service conversations don't require marketing consent
            ])->save();
        } else {
            $contact->update(['last_seen_at' => now()]);
        }

        return $contact;
    }

    protected function resolveConversation(Store $store, Contact $contact, WhatsappEvent $event): Conversation
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
            'source' => 'inbound',
        ]);
    }

    protected function handleStatus(Store $store, WhatsappEvent $event): void
    {
        if (! $event->messageId) {
            return;
        }

        $message = Message::where('provider_message_id', $event->messageId)
            ->where('store_id', $store->id)
            ->first();

        if (! $message) {
            return;
        }

        $status = strtolower(data_get($event->raw, 'status', ''));
        $map = [
            'delivered' => 'delivered_at',
            'read' => 'read_at',
        ];

        if (isset($map[$status])) {
            $message->update(['status' => $status, $map[$status] => now()]);
        }
    }

    protected function broadcast(Conversation $conversation, Message $message): void
    {
        try {
            broadcast(new \App\Events\InboxMessageEvent($conversation, $message));
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->warning('Broadcast failed', ['error' => $e->getMessage()]);
        }
    }
}
