<?php

namespace App\Services\Ai\Tools;

use App\Models\Order;

class OrderDetailsTool extends Tool
{
    public function name(): string
    {
        return 'order_details';
    }

    public function description(): string
    {
        return 'Return full line-item and pricing detail for a customer order.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_reference' => ['type' => 'string', 'description' => 'Order number'],
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
            return ToolResult::fail("Order {$ref} was not found.");
        }

        return ToolResult::ok([
            'order_number' => '#'.$order->order_number,
            'placed_at' => $order->placed_at?->format('M j, Y'),
            'subtotal' => $order->subtotal_price,
            'discounts' => $order->total_discounts,
            'shipping' => $order->total_shipping,
            'tax' => $order->total_tax,
            'total' => $order->total_price,
            'currency' => $order->currency,
            'line_items' => collect($order->line_items ?? [])->map(fn ($li) => [
                'title' => $li['title'] ?? null,
                'quantity' => $li['quantity'] ?? 1,
                'price' => $li['price'] ?? null,
            ])->all(),
        ]);
    }
}
