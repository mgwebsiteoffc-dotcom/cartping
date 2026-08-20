<?php

namespace App\Services\Ai\Tools;

class StoreInfoTool extends Tool
{
    public function name(): string
    {
        return 'store_info';
    }

    public function description(): string
    {
        return 'Return store-wide facts: address, hours, phone, policies summary. Use for general store questions.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'topic' => ['type' => 'string', 'description' => 'Optional: hours, location, contact, policies, shipping'],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $settings = $context->store->settings ?? [];

        return ToolResult::ok([
            'store_name' => $context->store->name,
            'domain' => $context->store->myshopify_domain,
            'hours' => $settings['hours'] ?? 'Mon-Fri 9am-6pm',
            'address' => $settings['address'] ?? null,
            'contact_email' => $context->store->contact_email,
            'contact_phone' => $settings['phone'] ?? null,
            'currency' => $context->store->currency,
            'policies' => $settings['policies'] ?? [],
        ]);
    }
}
