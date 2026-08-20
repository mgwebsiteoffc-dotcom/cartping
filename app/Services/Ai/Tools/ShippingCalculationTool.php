<?php

namespace App\Services\Ai\Tools;

use App\Models\Product;

class ShippingCalculationTool extends Tool
{
    public function name(): string
    {
        return 'shipping_calculation';
    }

    public function description(): string
    {
        return 'Estimate shipping cost and delivery time for a destination. Uses the store shipping profile rates.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'country' => ['type' => 'string', 'description' => 'Destination country, e.g. "US"'],
                'zip' => ['type' => 'string', 'description' => 'Destination postal code (optional)'],
                'weight_grams' => ['type' => 'number', 'description' => 'Total package weight in grams (optional)'],
            ],
            'required' => ['country'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $rates = $context->store->settings['shipping_rates'] ?? [];

        // Fallback defaults for demonstration when no profile is configured.
        $defaults = [
            ['name' => 'Standard', 'cost' => 5.00, 'days' => '5-7 business days', 'min_country' => null],
            ['name' => 'Express', 'cost' => 12.00, 'days' => '2-3 business days', 'min_country' => null],
        ];

        $rates = $rates ?: $defaults;
        $country = strtoupper($arguments['country'] ?? 'US');

        $result = collect($rates)
            ->filter(fn ($r) => ($r['min_country'] ?? null) === null || strtoupper((string) $r['min_country']) === $country)
            ->map(fn ($r) => [
                'method' => $r['name'],
                'cost' => number_format((float) $r['cost'], 2).' '.($context->store->currency ?? 'USD'),
                'eta' => $r['days'] ?? 'est. delivery provided at checkout',
            ])->values()->all();

        if (! $result) {
            return ToolResult::fail("I couldn't find shipping rates for {$country}. Please enter a different country.");
        }

        return ToolResult::ok(['destination' => $country, 'rates' => $result]);
    }
}
