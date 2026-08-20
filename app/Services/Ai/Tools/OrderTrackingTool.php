<?php

namespace App\Services\Ai\Tools;

use App\Models\Order;

class OrderTrackingTool extends Tool
{
    public function name(): string
    {
        return 'order_tracking';
    }

    public function description(): string
    {
        return 'Look up the current status and shipping tracking details of a customer order by order number or id.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'order_reference' => ['type' => 'string', 'description' => 'Shopify order name/number (e.g. #1001) or the numeric order id'],
                'email' => ['type' => 'string', 'description' => 'Customer email, used to confirm identity'],
            ],
            'required' => ['order_reference'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $ref = trim($arguments['order_reference'] ?? '');
        if (! $ref) {
            return ToolResult::fail('No order reference provided.');
        }

        $order = Order::query()
            ->where('store_id', $context->store->id)
            ->where(function ($q) use ($ref) {
                $q->where('order_number', ltrim($ref, '#'))
                    ->orWhere('shopify_order_id', $ref);
            })
            ->first();

        if (! $order) {
            return ToolResult::fail("I couldn't find order {$ref}. Could you double-check the order number, or share the email used at checkout?");
        }

        return ToolResult::ok([
            'order_number' => '#'.$order->order_number,
            'status' => $order->status,
            'financial_status' => $order->financial_status,
            'fulfillment_status' => $order->fulfillment_status,
            'tracking_number' => $order->tracking_number,
            'tracking_company' => $order->tracking_company,
            'tracking_url' => $order->tracking_url,
            'placed_at' => $order->placed_at?->toIso8601String(),
            'total' => number_format($order->total_price, 2).' '.$order->currency,
            'items' => $order->shortLineSummary(),
        ]);
    }
}
