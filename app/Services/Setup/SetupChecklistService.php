<?php

namespace App\Services\Setup;

use App\Models\Store;

/**
 * Assesses a store's configuration health and returns a checklist of what's
 * set up vs. missing — very useful on shared hosting where pieces (queue cron,
 * webhook URLs) are easy to forget.
 */
class SetupChecklistService
{
    public function checklist(Store $store): array
    {
        return [
            $this->shopify($store),
            $this->whatsappProvider($store),
            $this->whatsappConnected($store),
            $this->agent($store),
            $this->template($store),
            $this->product($store),
            $this->plan($store),
            $this->queueCron($store),
            $this->broadcast($store),
            $this->onboarding($store),
        ];
    }

    protected function shopify(Store $store): array
    {
        $connected = (bool) $store->shopifyConnection?->access_token;

        return [
            'key' => 'shopify',
            'label' => 'Shopify connection',
            'ok' => $connected,
            'hint' => $connected ? 'Connected (v'.config('shopify.api_version').')' : 'Connect Shopify in Settings → Shopify',
            'action' => $connected ? null : 'settings.shopify',
        ];
    }

    protected function whatsappProvider(Store $store): array
    {
        $conn = $store->whatsappConnection;

        return [
            'key' => 'provider',
            'label' => 'WhatsApp provider configured',
            'ok' => (bool) $conn?->provider,
            'hint' => $conn?->provider
                ? ucfirst($conn->provider).' selected'
                : 'Pick Meta or Whatify in Settings → WhatsApp',
            'action' => $conn?->provider ? null : 'settings.whatsapp',
        ];
    }

    protected function whatsappConnected(Store $store): array
    {
        $conn = $store->whatsappConnection;

        return [
            'key' => 'whatsapp_connected',
            'label' => 'WhatsApp connected',
            'ok' => (bool) ($conn?->is_connected),
            'hint' => ($conn?->is_connected ? 'Connected' : 'Not connected — check credentials in Settings → WhatsApp'),
            'action' => $conn?->is_connected ? null : 'settings.whatsapp',
        ];
    }

    protected function agent(Store $store): array
    {
        $cfg = $store->agentConfig;

        return [
            'key' => 'agent',
            'label' => 'AI store agent',
            'ok' => (bool) ($cfg?->enabled),
            'hint' => $cfg?->enabled ? 'Enabled' : 'Enable the AI agent',
            'action' => $cfg?->enabled ? null : 'agent.index',
        ];
    }

    protected function template(Store $store): array
    {
        $has = $store->templates()->where('status', 'approved')->exists();

        return [
            'key' => 'template',
            'label' => 'Approved message template',
            'ok' => $has,
            'hint' => $has ? 'You have an approved template' : 'Create & approve a template (notifications need one)',
            'action' => $has ? null : 'templates.index',
        ];
    }

    protected function product(Store $store): array
    {
        $has = \App\Models\Product::where('store_id', $store->id)->exists();

        return [
            'key' => 'products',
            'label' => 'Products synced',
            'ok' => $has,
            'hint' => $has ? 'Catalog available for AI search' : 'Sync products in the Products page',
            'action' => $has ? null : 'products.index',
        ];
    }

    protected function plan(Store $store): array
    {
        $planName = $store->plan?->name ?? 'Free';
        $msgs = $store->messagesRemaining();

        return [
            'key' => 'plan',
            'label' => 'Plan & message usage',
            'ok' => true,
            'hint' => "{$planName} — {$msgs} messages remaining this month",
            'action' => null,
        ];
    }

    protected function queueCron(Store $store): array
    {
        // On shared hosting the queue cron must be running. We can't verify it
        // server-side reliably, so we surface it as advisory with guidance.
        $isDatabaseQueue = config('queue.default') === 'database';

        return [
            'key' => 'queue',
            'label' => 'Queue worker / cron',
            'ok' => ! $isDatabaseQueue,
            'hint' => $isDatabaseQueue
                ? 'Using database queue — ensure a cron runs `artisan queue:work --once` every minute'
                : 'Queue is running (non-database driver)',
            'action' => null,
        ];
    }

    protected function broadcast(Store $store): array
    {
        $isLog = config('broadcasting.default') === 'log';

        return [
            'key' => 'broadcast',
            'label' => 'Real-time inbox',
            'ok' => ! $isLog,
            'hint' => $isLog
                ? 'Broadcast=log (shared hosting) — inbox updates by polling, not live WebSockets'
                : 'Real-time broadcasts enabled',
            'action' => null,
        ];
    }

    protected function onboarding(Store $store): array
    {
        return [
            'key' => 'onboarding',
            'label' => 'Onboarding complete',
            'ok' => (bool) $store->onboarding_complete,
            'hint' => $store->onboarding_complete ? 'Complete' : 'Finish the onboarding wizard',
            'action' => $store->onboarding_complete ? null : 'onboarding.index',
        ];
    }
}
