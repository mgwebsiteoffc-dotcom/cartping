<?php

namespace App\Services\Ai\Tools;

use App\Models\Conversation;
use App\Models\Store;

/**
 * Base contract for a callable agent tool. Every tool exposes:
 *  - an OpenAI-compatible JSON schema for function calling
 *  - a runtime execute() given parsed arguments and agent context
 */
abstract class Tool
{
    abstract public function name(): string;

    abstract public function description(): string;

    /**
     * @return array{type: string, properties: array, required: array}
     */
    abstract public function parameters(): array;

    abstract public function execute(array $arguments, ToolContext $context): ToolResult;

    /**
     * OpenAI function-calling schema entry.
     */
    public function schema(): array
    {
        return [
            'type' => 'function',
            'function' => [
                'name' => $this->name(),
                'description' => $this->description(),
                'parameters' => $this->parameters(),
            ],
        ];
    }
}
