<?php

namespace App\Services\Ai\Tools;

use App\Models\Product;

class ProductSearchTool extends Tool
{
    public function name(): string
    {
        return 'product_search';
    }

    public function description(): string
    {
        return 'Search the store catalog for products matching a query. Returns title, price and availability.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Free-text search query, e.g. "wireless earbuds"'],
                'limit' => ['type' => 'integer', 'description' => 'Max results to return', 'default' => 5],
            ],
            'required' => ['query'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $query = trim($arguments['query'] ?? '');
        $limit = min((int) ($arguments['limit'] ?? 5), 10);

        if (! $query) {
            return ToolResult::fail('No search query provided.');
        }

        $products = Product::query()
            ->where('store_id', $context->store->id)
            ->where('status', 'active')
            ->where(fn ($q) => $q->where('title', 'like', "%{$query}%")
                ->orWhere('product_type', 'like', "%{$query}%")
                ->orWhere('tags', 'like', "%{$query}%")
                ->orWhere('body_html', 'like', "%{$query}%"))
            ->limit($limit)
            ->get(['title', 'price_min', 'price_max', 'currency', 'available', 'handle', 'featured_image']);

        if ($products->isEmpty()) {
            return ToolResult::fail("I couldn't find anything matching \"{$query}\". Try different keywords, or I can show you our bestsellers.");
        }

        return ToolResult::ok($products->map(fn ($p) => [
            'title' => $p->title,
            'price' => $p->price_min === $p->price_max
                ? number_format($p->price_min, 2).' '.$p->currency
                : number_format($p->price_min, 2).' - '.number_format($p->price_max, 2).' '.$p->currency,
            'available' => $p->available,
            'url' => 'https://'.$context->store->myshopify_domain.'/products/'.$p->handle,
            'image' => $p->featured_image,
        ])->all());
    }
}
