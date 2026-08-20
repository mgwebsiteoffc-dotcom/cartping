<?php

namespace App\Services\Ai\Tools;

use App\Models\WidgetSession;

class CartRecoveryTool extends Tool
{
    public function name(): string
    {
        return 'cart_recovery';
    }

    public function description(): string
    {
        return 'Send a recovery link for an abandoned cart that the customer left on the store.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'email' => ['type' => 'string', 'description' => 'Email used on the abandoned cart (optional)'],
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

        if (! $session) {
            return ToolResult::fail('I could not find an abandoned cart to recover.');
        }

        $items = $session->cart['line_items'] ?? [];

        return ToolResult::ok([
            'checkout_url' => $session->checkout_url,
            'items' => collect($items)->map(fn ($i) => ($i['quantity'] ?? 1).'x '.($i['title'] ?? 'Item'))->all(),
            'total' => $session->cartTotal(),
            'currency' => $session->cart['currency'] ?? $context->store->currency,
            'recovery_code' => strtoupper(substr(md5($session->id), 0, 8)),
        ]);
    }
}
