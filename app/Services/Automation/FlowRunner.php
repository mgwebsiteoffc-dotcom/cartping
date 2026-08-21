<?php

namespace App\Services\Automation;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Flow;
use App\Models\FlowRun;
use App\Models\Store;
use App\Services\Whatsapp\WhatsappSender;
use Illuminate\Support\Facades\Log;

/**
 * Executes a visual chat flow (graph of nodes) for a contact/conversation.
 *
 * Node types:
 *   start    – entry point
 *   message  – send a free-text WhatsApp message
 *   template – send an approved template (by template name)
 *   delay    – wait N seconds (pauses the run; resumed by the scheduler)
 *   condition– branch on contact attributes / message contains / order exists
 *   assign_human – hand off to a human agent
 *   end      – finish the flow
 *
 * Each node carries next / true_next / false_next pointers to node ids.
 * Runs are guarded by a max-steps cap to prevent infinite loops.
 */
class FlowRunner
{
    public const MAX_STEPS = 50;

    public function __construct(protected WhatsappSender $sender)
    {
    }

    /**
     * Start (or resume) a flow for a contact. If $flowRunId is given we resume
     * an existing paused run (e.g. after a delay).
     */
    public function run(Store $store, Flow $flow, Contact $contact, ?Conversation $conversation = null, ?FlowRun $existing = null): FlowRun
    {
        $run = $existing ?? FlowRun::create([
            'store_id' => $store->id,
            'flow_id' => $flow->id,
            'contact_id' => $contact->id,
            'conversation_id' => $conversation?->id,
            'state' => 'running',
            'steps' => [],
            'started_at' => now(),
        ]);

        $flow->markRun();

        $node = $this->findNode($flow, $run->current_node_id) ?: $flow->startNode();

        if (! $node) {
            $run->update(['state' => 'failed', 'error' => 'Flow has no start node.']);
            return $run;
        }

        $steps = 0;
        $trail = $run->steps ?? [];
        $current = $node;

        while ($current && $steps < self::MAX_STEPS) {
            $steps++;
            $type = $current['type'] ?? 'end';

            $trail[] = [
                'node_id' => $current['id'] ?? null,
                'type' => $type,
                'at' => now()->toIso8601String(),
            ];

            switch ($type) {
                case 'message':
                    $this->sendMessage($store, $contact, $conversation, $current);
                    $current = $this->next($flow, $current);
                    break;

                case 'template':
                    $this->sendTemplate($store, $contact, $conversation, $current);
                    $current = $this->next($flow, $current);
                    break;

                case 'delay':
                    $seconds = (int) ($current['data']['seconds'] ?? 0);
                    $run->update([
                        'current_node_id' => $current['id'],
                        'state' => 'running',
                        'steps' => $trail,
                        'metadata' => array_merge($run->metadata ?? [], [
                            'resume_at' => now()->addSeconds($seconds)->toIso8601String(),
                            'pending_delay' => true,
                        ]),
                    ]);

                    // Pause; the scheduler resumes after the delay.
                    $this->scheduleResume($run, $seconds);

                    return $run;

                case 'condition':
                    $pass = $this->evaluateCondition($contact, $conversation, $current);
                    $current = $pass
                        ? $this->findNode($flow, $current['true_next'] ?? null)
                        : $this->findNode($flow, $current['false_next'] ?? null);
                    break;

                case 'assign_human':
                    if ($conversation) {
                        $conversation->update([
                            'status' => 'pending_human',
                            'agent_mode' => 'human_takeover',
                            'escalated_reason' => $current['data']['reason'] ?? 'Flow assigned to human',
                        ]);
                    }
                    $current = $this->next($flow, $current);
                    break;

                case 'end':
                default:
                    $run->update(['state' => 'completed', 'finished_at' => now(), 'steps' => $trail]);
                    $flow->markCompletion();

                    return $run;
            }
        }

        if ($steps >= self::MAX_STEPS) {
            $run->update(['state' => 'failed', 'error' => 'Flow exceeded max steps (possible loop).']);
        }

        return $run;
    }

    /* ----------------------------- Node helpers -------------------------- */

    protected function findNode(Flow $flow, ?string $id): ?array
    {
        if (! $id) {
            return null;
        }

        foreach ($flow->nodes ?? [] as $node) {
            if (($node['id'] ?? null) === $id) {
                return $node;
            }
        }

        return null;
    }

    protected function next(Flow $flow, array $node): ?array
    {
        return $this->findNode($flow, $node['next'] ?? null);
    }

    protected function sendMessage(Store $store, Contact $contact, ?Conversation $conversation, array $node): void
    {
        $text = $node['data']['text'] ?? '';
        if ($text !== '') {
            $this->sender->text($store, $contact, $text, $conversation);
        }
    }

    protected function sendTemplate(Store $store, Contact $contact, ?Conversation $conversation, array $node): void
    {
        $templateName = $node['data']['template_name'] ?? null;
        if (! $templateName) {
            return;
        }

        $lang = $node['data']['language'] ?? 'en';
        $params = $node['data']['params'] ?? [];

        // Build components in the shape our provider abstraction expects.
        $components = [[
            'type' => 'body',
            'body' => [['text' => implode(', ', (array) $params)]],
            'parameters' => collect((array) $params)->map(fn ($v) => ['text' => $v])->values()->all(),
        ]];

        $this->sender->template($store, $contact, $templateName, $lang, $components, $conversation);
    }

    protected function evaluateCondition(?Contact $contact, ?Conversation $conversation, array $node): bool
    {
        $field = $node['data']['field'] ?? null;
        $operator = $node['data']['operator'] ?? 'exists';
        $value = $node['data']['value'] ?? null;

        $actual = match ($field) {
            'contact.opted_in' => (bool) ($contact?->hasOptedIn()),
            'contact.name', 'contact.profile_name' => $contact?->profile_name,
            'contact.consent' => $contact?->consent_state,
            'conversation.source' => $conversation?->source,
            'message_contains' => $value ? true : false, // set by caller via metadata
            default => $contact?->metadata[$field] ?? null,
        };

        // For message_contains we use the last inbound body from conversation meta.
        if ($field === 'message_contains') {
            $lastBody = mb_strtolower((string) ($conversation?->meta['last_inbound_body'] ?? ''));
            $needle = mb_strtolower((string) $value);
            return $needle !== '' && str_contains($lastBody, $needle);
        }

        return match ($operator) {
            'exists' => $actual !== null && $actual !== '',
            'not_exists' => $actual === null || $actual === '',
            'equals' => (string) $actual === (string) $value,
            'contains' => is_string($actual) && str_contains(mb_strtolower($actual), mb_strtolower((string) $value)),
            default => (bool) $actual,
        };
    }

    protected function scheduleResume(FlowRun $run, int $seconds): void
    {
        if ($seconds <= 0) {
            // Resume immediately via queue.
            \App\Jobs\ResumeFlow::dispatch($run)->onQueue('default');
            return;
        }

        \App\Jobs\ResumeFlow::dispatch($run)
            ->onQueue('default')
            ->delay(now()->addSeconds($seconds));
    }
}
