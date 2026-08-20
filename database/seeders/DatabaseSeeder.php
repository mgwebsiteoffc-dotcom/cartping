<?php

namespace Database\Seeders;

use App\Models\AgentConfig;
use App\Models\Automation;
use App\Models\KnowledgeBaseChunk;
use App\Models\Store;
use App\Models\Template;
use App\Models\WidgetConfig;
use App\Services\Templates\ComplianceChecker;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo tenant with sensible defaults so a fresh install is explorable.
        $store = Store::firstOrCreate(
            ['myshopify_domain' => 'demo.myshopify.com'],
            [
                'name' => 'Demo Store',
                'contact_email' => 'demo@example.com',
                'password' => 'password',
                'onboarding_step' => 6,
                'onboarding_complete' => true,
            ]
        );

        AgentConfig::firstOrCreate(['store_id' => $store->id], [
            'name' => 'Demo Assistant',
            'enabled' => true,
            'autonomous' => true,
            'rag_enabled' => true,
            'persona' => 'Friendly, concise assistant for the demo store.',
        ]);

        WidgetConfig::firstOrCreate(['store_id' => $store->id], [
            'type' => 'smart_contextual',
            'enabled' => true,
            'install_mode' => 'manual',
        ]);

        $this->seedAutomations($store);
        $this->seedTemplates($store);
        $this->seedKnowledgeBase($store);
    }

    protected function seedAutomations(Store $store): void
    {
        $definitions = [
            ['name' => 'Order confirmation', 'trigger' => 'order_created', 'delay' => 0],
            ['name' => 'Shipping update', 'trigger' => 'order_shipped', 'delay' => 0],
            ['name' => 'Abandoned cart 1h', 'trigger' => 'abandoned_checkout', 'delay' => 60],
            ['name' => 'Abandoned cart 24h', 'trigger' => 'abandoned_checkout', 'delay' => 1440],
            ['name' => 'Abandoned browse 2h', 'trigger' => 'abandoned_browse', 'delay' => 120],
            ['name' => 'Welcome flow', 'trigger' => 'welcome', 'delay' => 0],
        ];

        foreach ($definitions as $def) {
            Automation::firstOrCreate(
                ['store_id' => $store->id, 'name' => $def['name']],
                ['trigger' => $def['trigger'], 'delay_after_minutes' => $def['delay'], 'is_active' => true]
            );
        }
    }

    protected function seedTemplates(Store $store): void
    {
        $checker = new ComplianceChecker();

        Template::firstOrCreate(
            ['store_id' => $store->id, 'name' => 'order_confirmation'],
            [
                'display_name' => 'Order Confirmation',
                'category' => 'UTILITY',
                'language' => 'en',
                'body' => "Hi {{1}}, thanks for your order #{{2}}! We'll email tracking as soon as it ships.",
                'status' => 'approved',
                'lifecycle' => 'approved',
                'compliance_issues' => $checker->check("Hi {{1}}, thanks for your order #{{2}}! We'll email tracking as soon as it ships.", ['category' => 'UTILITY']),
            ]
        );
    }

    protected function seedKnowledgeBase(Store $store): void
    {
        KnowledgeBaseChunk::firstOrCreate(
            ['store_id' => $store->id, 'title' => 'Returns policy'],
            [
                'source_type' => 'policy',
                'content' => 'Returns are accepted within 30 days of delivery. Items must be unused and in original packaging. Refunds are issued to the original payment method within 5 business days.',
                'keywords' => ['return', 'refund', '30 days', 'policy'],
            ]
        );

        KnowledgeBaseChunk::firstOrCreate(
            ['store_id' => $store->id, 'title' => 'Shipping times'],
            [
                'source_type' => 'faq',
                'content' => 'Standard shipping takes 5-7 business days. Express shipping takes 2-3 business days. International orders may take up to 14 business days.',
                'keywords' => ['shipping', 'delivery', 'how long'],
            ]
        );
    }
}
