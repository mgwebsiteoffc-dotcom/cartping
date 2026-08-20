<?php

namespace App\Services\Templates;

use App\Models\Store;
use App\Services\Ai\OpenRouterClient;

/**
 * Uses the reasoning model (e.g. nvidia/nemotron-3.5-lightning:free) to draft
 * WhatsApp templates from a natural-language brief — including A/B variants.
 * Mirrors the reasoning loop from the reference OpenRouter script.
 */
class AiTemplateGenerator
{
    public function __construct(protected OpenRouterClient $client)
    {
    }

    /**
     * Generate a template (and optional A/B variants) from a brief.
     *
     * @return array{
     *   name: string, category: string, language: string, body: string,
     *   variants: array<int, array{label: string, body: string}>
     * }
     */
    public function generate(Store $store, string $brief, int $variants = 2): array
    {
        $task = $this->briefToTask($store, $brief);

        $fields = ['name', 'category', 'language', 'body', 'variants'];

        return $this->client->structuredJson($task, $fields, config('ai.reasoning_model'));
    }

    protected function briefToTask(Store $store, string $brief): string
    {
        return <<<TASK
Create a WhatsApp message template for {$store->name} (a Shopify store).

Merchant brief: {$brief}

Requirements:
- category must be one of MARKETING, UTILITY, AUTHENTICATION.
- language: en (US).
- body: concise, professional, uses {{1}} as a personalisation variable where useful.
- Emit ONLY a valid JSON object with exactly these keys: name, category, language, body, variants.
- variants must be an array of 2 objects with keys "label" ("A" and "B") and "body" — each a distinct alternative wording of the same message.
- Do not wrap the JSON in markdown fences.
TASK;
    }
}
