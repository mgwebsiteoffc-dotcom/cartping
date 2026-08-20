<?php

namespace App\Services\Ai;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Store;
use App\Services\Ai\Tools\ToolContext;
use App\Services\Ai\Tools\ToolRegistry;
use App\Services\Ai\Tools\ToolResult;

/**
 * Runs the conversational store agent: builds the 8-layer prompt, calls the
 * model with function-calling tools, and executes tool calls in a loop until
 * the model produces a final answer (or we hit the round limit / escalate).
 */
class AgentOrchestrator
{
    public function __construct(
        protected OpenRouterClient $client,
        protected PromptBuilder $prompt,
        protected ToolRegistry $tools,
        protected RagRetriever $rag,
    ) {
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $history
     * @return array{
     *   content: ?string,
     *   tool_calls: array,
     *   escalated: bool,
     *   escalate_reason: ?string,
     *   confidence: float,
     *   rounds: int,
     *   usage: ?array
     * }
     */
    public function respond(
        Store $store,
        string $userMessage,
        ?Contact $contact = null,
        ?Conversation $conversation = null,
        array $history = [],
    ): array {
        $agent = $store->agentConfig;
        $model = $agent?->resolveModel() ?? config('ai.model');

        $chunks = $agent?->rag_enabled
            ? $this->rag->retrieve($store, $userMessage)->all()
            : [];

        $system = $this->prompt->build($store, $contact, $conversation, $history, $chunks);

        $messages = [['role' => 'system', 'content' => $system]];
        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $schemas = $this->tools->schemasFor($store);

        $toolCallsMade = [];
        $escalated = false;
        $escalateReason = null;
        $confidence = 0.8;

        $maxRounds = config('ai.max_tool_rounds', 8);

        for ($round = 0; $round < $maxRounds; $round++) {
            $result = $this->client->chat($messages, $model, tools: $schemas);

            $message = $result['message'];
            $toolCalls = $message['tool_calls'] ?? [];

            if ($toolCalls === []) {
                // Final answer.
                return [
                    'content' => $message['content'] ?? null,
                    'tool_calls' => $toolCallsMade,
                    'escalated' => $escalated,
                    'escalate_reason' => $escalateReason,
                    'confidence' => $confidence,
                    'rounds' => $round + 1,
                    'usage' => $result['usage'] ?? null,
                ];
            }

            $messages[] = [
                'role' => 'assistant',
                'content' => $message['content'] ?? null,
                'tool_calls' => $toolCalls,
            ];

            foreach ($toolCalls as $call) {
                $name = $call['function']['name'] ?? null;
                $arguments = json_decode($call['function']['arguments'] ?? '{}', true) ?: [];

                $toolCallsMade[] = ['name' => $name, 'arguments' => $arguments];

                if (! $this->tools->has($name)) {
                    $messages[] = [
                        'role' => 'tool',
                        'tool_call_id' => $call['id'] ?? null,
                        'content' => json_encode(['success' => false, 'error' => "Unknown tool [{$name}]"]),
                    ];
                    continue;
                }

                $tool = $this->tools->get($name);
                $context = new ToolContext($store, $contact, $conversation);

                try {
                    $toolResult = $tool->execute($arguments, $context);
                } catch (\Throwable $e) {
                    $toolResult = ToolResult::fail('Tool execution error: '.$e->getMessage(), escalate: true, escalateReason: 'Tool error');
                }

                if ($toolResult->escalate) {
                    $escalated = true;
                    $escalateReason = $toolResult->escalateReason;
                    $confidence = 0.0;
                }

                $messages[] = [
                    'role' => 'tool',
                    'tool_call_id' => $call['id'] ?? null,
                    'content' => json_encode($toolResult->jsonSerialize()),
                ];
            }

            $schemas = []; // disable tools on the wrap-up turn so it must answer
        }

        // Round limit reached without a final answer.
        $escalated = true;
        $escalateReason = $escalateReason ?: 'Agent reached its tool-call limit without a confident answer';

        return [
            'content' => 'Let me get a human agent to help with this — one moment.',
            'tool_calls' => $toolCallsMade,
            'escalated' => true,
            'escalate_reason' => $escalateReason,
            'confidence' => 0.0,
            'rounds' => $maxRounds,
            'usage' => null,
        ];
    }
}
