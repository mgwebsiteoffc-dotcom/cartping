<?php

namespace Tests\Unit;

use App\Models\AgentConfig;
use App\Models\Store;
use App\Services\Ai\PromptBuilder;
use App\Services\Ai\Tools\ToolRegistry;
use PHPUnit\Framework\TestCase;

class PromptBuilderTest extends TestCase
{
    public function test_build_contains_all_8_prompt_layers(): void
    {
        $builder = new PromptBuilder(new ToolRegistry());

        $store = new Store([
            'name' => 'Demo Store',
            'myshopify_domain' => 'demo.myshopify.com',
            'currency' => 'USD',
            'timezone' => 'UTC',
            'contact_email' => 'hello@demo.com',
        ]);
        $store->setRelation('agentConfig', new AgentConfig(['autonomous' => true, 'rag_enabled' => true]));

        $prompt = $builder->build($store, null, null, [
            ['role' => 'user', 'content' => 'Hi'],
            ['role' => 'assistant', 'content' => 'Hello!'],
        ]);

        foreach (['1. BASE INSTRUCTIONS', '2. STORE CONTEXT', '3. CUSTOMER CONTEXT', '4. CONVERSATION HISTORY', '5. TOOL DEFINITIONS', '6. RESPONSE FORMATTING', '7. ESCALATION TRIGGERS', '8. KNOWLEDGE BASE'] as $layer) {
            $this->assertStringContainsString($layer, $prompt);
        }
    }
}
