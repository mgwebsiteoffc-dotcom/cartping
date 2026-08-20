<?php

namespace App\Services\Ai;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\KnowledgeBaseChunk;
use App\Models\Store;
use App\Services\Ai\Tools\ToolRegistry;

/**
 * Builds the 8-layer system prompt for the conversational store agent:
 *
 *  1. Base instructions      – role, behavior, guardrails
 *  2. Store context          – brand, catalog summary, policies
 *  3. Customer context       – identity, order history, LTV, consent
 *  4. Conversation history   – recent exchange (rolled for token budget)
 *  5. Tool definitions       – how to use function-calling tools
 *  6. Response formatting    – tone, length, Markdown/emoji rules
 *  7. Escalation triggers    – when to call escalate_to_human
 *  8. RAG knowledge chunks   – retrieved KB snippets
 */
class PromptBuilder
{
    public function __construct(protected ToolRegistry $tools)
    {
    }

    public function build(
        Store $store,
        ?Contact $contact,
        ?Conversation $conversation,
        array $history = [],
        array $ragChunks = [],
    ): array {
        $agent = $store->agentConfig;

        $layers = [
            $this->baseInstructions($store, $agent),
            $this->storeContext($store, $agent),
            $this->customerContext($store, $contact),
            $this->conversationHistory($history),
            $this->toolDefinitions($store),
            $this->responseFormatting(),
            $this->escalationTriggers($agent?->escalation_triggers),
            $this->ragChunks($ragChunks),
        ];

        // Layer 2 (store context) is the primary system prompt; the rest are
        // appended so the model sees a coherent hierarchy.
        return implode("\n\n", $layers);
    }

    protected function baseInstructions(Store $store, ?\App\Models\AgentConfig $agent): string
    {
        $name = $agent?->name ?? 'your store assistant';
        $persona = $agent?->persona
            ?? "You are a friendly, knowledgeable assistant for {$store->name}. "
               .'Help customers with orders, products, shipping, returns and anything else about the store.';

        return <<<PROMPT
# 1. BASE INSTRUCTIONS
You are {$name} for {$store->name} on WhatsApp.

{$persona}

Rules you must always follow:
- Only answer using facts you obtain from your tools or from the provided store/customer context. Never invent order numbers, prices, stock, or delivery times.
- If you do not have enough information, ask a clarifying question or escalate to a human.
- Be concise, warm and helpful. Prefer short WhatsApp-style messages (a few sentences max unless detail is required).
- Never request or confirm sensitive data (full payment card numbers, passwords) in plain text.
- Respect the customer's privacy: do not reveal another customer's data.
PROMPT;
    }

    protected function storeContext(Store $store, ?\App\Models\AgentConfig $agent): string
    {
        $settings = $store->settings ?? [];
        $summary = sprintf(
            "Store: %s (%s) | Currency: %s | Timezone: %s",
            $store->name,
            $store->myshopify_domain,
            $store->currency,
            $store->timezone,
        );

        $extra = $settings['agent_context'] ?? '';

        return <<<PROMPT
# 2. STORE CONTEXT
{$summary}
{$extra}
Store contact email: {$store->contact_email}
Agent auto-mode: {$agent?->autonomous ? 'resolves most requests autonomously' : 'resolves common requests, escalates complex ones'}
PROMPT;
    }

    protected function customerContext(Store $store, ?Contact $contact): string
    {
        if (! $contact) {
            return <<<PROMPT
# 3. CUSTOMER CONTEXT
New customer (WhatsApp {$store->myshopify_domain}). No profile linked yet.
PROMPT;
        }

        $customer = $contact->shopifyCustomer;
        $orders = $customer
            ? "Orders: {$customer->total_orders}, Lifetime value: {$customer->lifetime_value} {$store->currency}"
            : 'No Shopify order history yet.';

        return <<<PROMPT
# 3. CUSTOMER CONTEXT
Name: {$contact->profile_name ?: 'unknown'}
WA: {$contact->wa_id}
Consent: {$contact->consent_state} (opted in {$contact->opt_in_source ?? 'n/a'})
{$orders}
PROMPT;
    }

    protected function conversationHistory(array $history): string
    {
        if ($history === []) {
            return '# 4. CONVERSATION HISTORY\n(no prior messages in this session)';
        }

        $lines = array_map(
            fn ($m) => '['.($m['role'] ?? 'system').'] '.($m['content'] ?? ''),
            $history
        );

        return "# 4. CONVERSATION HISTORY\n".implode("\n", array_slice($lines, -12));
    }

    protected function toolDefinitions(Store $store): string
    {
        $tools = implode(', ', $this->tools->schemasFor($store)
            ? array_map(fn ($s) => $s['function']['name'], $this->tools->schemasFor($store))
            : ['none']);

        return <<<PROMPT
# 5. TOOL DEFINITIONS
You have access to these function-calling tools. ALWAYS call a tool when you need a fact rather than guessing:
{$tools}
To complete checkout, shipping, returns or tracking use the appropriate tool and pass back the link or details.
PROMPT;
    }

    protected function responseFormatting(): string
    {
        return <<<PROMPT
# 6. RESPONSE FORMATTING
- Keep messages short and scannable on a phone screen.
- Use minimal emoji; no markdown headers or code blocks.
- When sharing a link, give a friendly label followed by the URL.
- If you used a tool, reflect its result accurately in your reply.
- If a tool returns "not found", apologise and offer the next best step.
PROMPT;
    }

    protected function escalationTriggers(?array $triggers): string
    {
        $extra = $triggers && $triggers !== []
            ? 'Merchant-configured triggers: '.implode(', ', array_map(fn ($t) => (string) $t, $triggers)).'.'
            : '';

        return <<<PROMPT
# 7. ESCALATION TRIGGERS
Call the escalate_to_human tool immediately when ANY of the following apply:
- The customer explicitly asks to speak to a human or is repeatedly frustrated.
- The request involves legal, account-security, payment-dispute, or refund-over-limit matters.
- You are unsure of the correct answer after your best attempt.
- The customer is abusive or threatens harm.
You must give a clear reason for the escalation. {$extra}
PROMPT;
    }

    protected function ragChunks(array $chunks): string
    {
        if ($chunks === []) {
            return '# 8. KNOWLEDGE BASE\n(no relevant knowledge retrieved for this query)';
        }

        $sections = collect($chunks)
            ->map(fn (KnowledgeBaseChunk $c) => "- [{$c->source_type}] {$c->title}: {$c->content}")
            ->implode("\n");

        return <<<PROMPT
# 8. KNOWLEDGE BASE (retrieved, use to answer when relevant)
{$sections}
PROMPT;
    }
}
