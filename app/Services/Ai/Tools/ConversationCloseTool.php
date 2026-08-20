<?php

namespace App\Services\Ai\Tools;

class ConversationCloseTool extends Tool
{
    public function name(): string
    {
        return 'conversation_close';
    }

    public function description(): string
    {
        return 'Mark the conversation as resolved once the customer has no further questions.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'summary' => ['type' => 'string', 'description' => 'Short summary of what was resolved'],
            ],
            'required' => ['summary'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if ($context->conversation) {
            $context->conversation->update([
                'status' => 'resolved',
                'resolved_at' => now(),
                'meta' => array_merge($context->conversation->meta ?? [], [
                    'resolution_summary' => $arguments['summary'] ?? null,
                ]),
            ]);
        }

        return ToolResult::ok(['resolved' => true]);
    }
}
