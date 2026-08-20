<?php

namespace App\Services\Ai\Tools;

class EscalateToHumanTool extends Tool
{
    public function name(): string
    {
        return 'escalate_to_human';
    }

    public function description(): string
    {
        return 'Hand the conversation to a human support agent. Use when the customer asks for a human, is upset, the request is complex, or you are unsure.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'reason' => ['type' => 'string', 'description' => 'Why this should be escalated to a human'],
            ],
            'required' => ['reason'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if ($context->conversation) {
            $context->conversation->update([
                'status' => 'pending_human',
                'agent_mode' => 'human_takeover',
                'escalated_reason' => $arguments['reason'] ?? 'Customer requested a human',
                'ai_confidence' => 0.0,
            ]);
        }

        return new ToolResult(
            data: [
                'escalated' => true,
                'message' => 'This conversation has been handed to a human agent and they will reply shortly.',
            ],
            escalate: true,
            escalateReason: $arguments['reason'] ?? 'Customer requested a human',
        );
    }
}
