<?php

namespace App\Services\Ai\Tools;

use App\Models\ShopifyCustomer;

class LtvTool extends Tool
{
    public function name(): string
    {
        return 'customer_ltv';
    }

    public function description(): string
    {
        return 'Return lifetime value and order count for the current customer. Use to personalise loyalty responses.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'email' => ['type' => 'string', 'description' => 'Customer email (optional, to confirm identity)'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        if (! $context->contact?->shopify_customer_id) {
            return ToolResult::ok(['known' => false, 'message' => 'No loyalty history found for this customer yet.']);
        }

        $customer = ShopifyCustomer::find($context->contact->shopify_customer_id);

        return ToolResult::ok([
            'known' => true,
            'total_orders' => $customer->total_orders,
            'lifetime_value' => $customer->lifetime_value,
            'currency' => $context->store->currency,
            'last_order_at' => $customer->last_order_at?->format('M j, Y'),
        ]);
    }
}
