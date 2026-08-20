<?php

namespace App\Services\Ai\Tools;

use App\Models\WidgetSession;

/**
 * Generates a fresh Shopify checkout link for the customer's cart. If the
 * customer has an active widget session with a cart token, we reuse it;
 * otherwise we return the store checkout URL.
 */
class CheckoutLinkTool extends Tool
{
    public function name(): string
    {
        return 'checkout_link';
    }

    public function description(): string
    {
        return 'Generate a secure link for the customer to complete checkout of their current cart.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'product' => ['type' => 'string', 'description' => 'Optional product name to buy now if no cart exists'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $session = WidgetSession::query()
            ->where('store_id', $context->store->id)
            ->where('contact_id', $context->contact?->id)
            ->whereNotNull('checkout_url')
            ->latest('last_active_at')
            ->first();

        if ($session?->checkout_url) {
            return ToolResult::ok([
                'checkout_url' => $session->checkout_url,
                'cart_items' => count($session->cart['line_items'] ?? []),
                'cart_total' => $session->cartTotal(),
            ]);
        }

        $product = $arguments['product'] ?? null;
        $url = $product
            ? 'https://'.$context->store->myshopify_domain.'/cart?search='.rawurlencode($product)
            : 'https://'.$context->store->myshopify_domain.'/checkout';

        return ToolResult::ok([
            'checkout_url' => $url,
            'note' => 'No active cart found; a generic checkout link was generated.',
        ]);
    }
}
