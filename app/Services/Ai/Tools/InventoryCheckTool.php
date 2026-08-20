<?php

namespace App\Services\Ai\Tools;

use App\Models\Product;

class InventoryCheckTool extends Tool
{
    public function name(): string
    {
        return 'inventory_check';
    }

    public function description(): string
    {
        return 'Check current stock availability for a product. Useful before promising an item is in stock.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'product_title' => ['type' => 'string', 'description' => 'Exact or partial product title'],
            ],
            'required' => ['product_title'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $title = trim($arguments['product_title'] ?? '');
        if (! $title) {
            return ToolResult::fail('No product title provided.');
        }

        $product = Product::query()
            ->where('store_id', $context->store->id)
            ->where('title', 'like', "%{$title}%")
            ->first();

        if (! $product) {
            return ToolResult::fail("I couldn't find that product to check stock.");
        }

        return ToolResult::ok([
            'title' => $product->title,
            'in_stock' => $product->available && $product->inventory_total > 0,
            'inventory_total' => $product->inventory_total,
            'backorder' => ($product->meta['allows_backorder'] ?? false),
        ]);
    }
}
