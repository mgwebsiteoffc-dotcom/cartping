<?php

namespace App\Services\Ai\Tools;

use App\Models\Order;

class ReturnInitiationTool extends Tool
{
    public function name(): string
    {
        return 'return_initiation';
    }

    public function description(): string
    {
        return 'Start a return request for an order and provide the return link / RMA instructions.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_reference' => ['type' => 'string', 'description' => 'Order number to return'],
                'reason' => ['type' => 'string', 'description' => 'Customer-provided return reason'],
            ],
            'required' => ['order_reference'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $ref = trim($arguments['order_reference'] ?? '');

        $order = Order::query()
            ->where('store_id', $context->store->id)
            ->where('order_number', ltrim($ref, '#'))
            ->first();

        if (! $order) {
            return ToolResult::fail("I couldn't find order {$ref} to start a return.");
        }

        if ($order->status === Order::STATUS_CANCELLED || $order->status === Order::STATUS_REFUNDED) {
            return ToolResult::fail('This order is already cancelled/refunded, so a return is not possible.');
        }

        $returnLink = 'https://'.$context->store->myshopify_domain.'/apps/returns?order='.$order->shopify_order_id;

        return ToolResult::ok([
            'order_number' => '#'.$order->order_number,
            'return_link' => $returnLink,
            'policy' => 'Returns accepted within 30 days of delivery in original condition.',
            'reason' => $arguments['reason'] ?? null,
            'logged' => true,
        ]);
    }
}
