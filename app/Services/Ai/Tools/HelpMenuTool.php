<?php

namespace App\Services\Ai\Tools;

class HelpMenuTool extends Tool
{
    public function name(): string
    {
        return 'help_menu';
    }

    public function description(): string
    {
        return 'Show the customer what you can help them with (a menu of capabilities).';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'topic' => ['type' => 'string', 'description' => 'Optional topic hint to tailor the menu'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        return ToolResult::ok([
            'capabilities' => [
                'order_tracking' => 'Track an order',
                'product_search' => 'Find a product',
                'inventory_check' => 'Check stock',
                'checkout_link' => 'Get a checkout link',
                'shipping_calculation' => 'Estimate shipping',
                'discount_validation' => 'Apply a discount',
                'return_initiation' => 'Start a return',
                'faq_search' => 'Answer a store question',
                'escalate_to_human' => 'Talk to a human',
            ],
        ]);
    }
}
