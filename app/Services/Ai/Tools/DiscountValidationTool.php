<?php

namespace App\Services\Ai\Tools;

class DiscountValidationTool extends Tool
{
    public function name(): string
    {
        return 'discount_validation';
    }

    public function description(): string
    {
        return 'Validate whether a discount code is active, what it applies to, and the discount amount.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'code' => ['type' => 'string', 'description' => 'The discount code the customer wants to use'],
            ],
            'required' => ['code'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $code = strtoupper(trim($arguments['code'] ?? ''));
        if (! $code) {
            return ToolResult::fail('No discount code provided.');
        }

        $discounts = $context->store->settings['discounts'] ?? [];

        $match = collect($discounts)->first(
            fn ($d) => strcasecmp($d['code'] ?? '', $code) === 0
        );

        if (! $match) {
            return ToolResult::fail("I couldn't find a valid discount code \"{$code}\". It may be expired or misspelled.");
        }

        if (! ($match['active'] ?? false)) {
            return ToolResult::fail("Discount \"{$code}\" is currently inactive.");
        }

        if (isset($match['expires_at']) && now()->gt($match['expires_at'])) {
            return ToolResult::fail("Discount \"{$code}\" has expired.");
        }

        return ToolResult::ok([
            'code' => $code,
            'type' => $match['type'] ?? 'percentage',
            'value' => $match['value'] ?? 0,
            'applies_to' => $match['applies_to'] ?? 'all',
            'minimum_order' => $match['minimum_order'] ?? null,
        ]);
    }
}
