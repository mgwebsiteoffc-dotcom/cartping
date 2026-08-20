<?php

namespace App\Services\Templates;

use App\Models\Store;
use App\Models\Template;
use App\Models\TemplateApproval;
use App\Models\TemplateVariant;
use App\Services\Whatsapp\WhatsappManager;
use Illuminate\Support\Str;

/**
 * Orchestrates the template lifecycle:
 *   draft -> compliance_review -> submitted -> in_review -> approved | rejected
 * with multi-level approvals (internal -> compliance -> provider) and A/B
 * variant submission.
 */
class TemplateService
{
    public function __construct(
        protected ComplianceChecker $compliance,
        protected AiTemplateGenerator $ai,
        protected WhatsappManager $whatsapp,
    ) {
    }

    public function createFromAi(Store $store, string $brief): Template
    {
        $generated = $this->ai->generate($store, $brief);

        $template = Template::create([
            'store_id' => $store->id,
            'name' => Str::slug($generated['name'] ?? 'template', '_'),
            'display_name' => $generated['name'] ?? 'New template',
            'category' => $generated['category'] ?? Template::CATEGORY_MARKETING,
            'language' => $generated['language'] ?? 'en',
            'body' => $generated['body'] ?? '',
            'status' => 'draft',
            'lifecycle' => 'draft',
            'approval_level' => 'internal',
        ]);

        $template->update(['compliance_issues' => $this->compliance->check($template->body, ['category' => $template->category])]);

        foreach (($generated['variants'] ?? []) as $i => $v) {
            $template->variants()->create([
                'store_id' => $store->id,
                'label' => $v['label'] ?? (($i === 0) ? 'A' : 'B'),
                'body' => $v['body'] ?? '',
                'status' => 'draft',
                'lifecycle' => 'draft',
                'is_control' => $i === 0,
            ]);
        }

        return $template->load('variants');
    }

    public function createAbVariants(Store $store, Template $template, int $count = 2): Template
    {
        // Derive simple alternates from the parent body for demonstration; a
        // production build would call the AI generator with "variant" intent.
        $prefixes = ['Great news!', 'Quick update:', 'Just a heads-up:'];

        for ($i = 0; $i < $count; $i++) {
            $template->variants()->create([
                'store_id' => $store->id,
                'label' => $i === 0 ? 'A' : (($i === 1) ? 'B' : chr(65 + $i)),
                'body' => $prefixes[$i % count($prefixes)].' '.$template->body,
                'status' => 'draft',
                'lifecycle' => 'draft',
            ]);
        }

        $template->update(['is_ab_test' => true]);

        return $template->load('variants');
    }

    /**
     * Multi-level approval. Levels progress: internal -> compliance -> provider.
     */
    public function approve(Store $store, Template $template, string $level, ?string $reviewer = null, ?string $comment = null): Template
    {
        $order = ['internal' => 1, 'compliance' => 2, 'provider' => 3];
        $current = $order[$template->approval_level] ?? 0;

        if (! isset($order[$level])) {
            throw new \InvalidArgumentException("Unknown approval level [{$level}].");
        }

        if ($order[$level] !== $current + 1) {
            throw new \RuntimeException("Out-of-order approval: {$template->approval_level} -> {$level}.");
        }

        TemplateApproval::create([
            'store_id' => $store->id,
            'template_id' => $template->id,
            'level' => $level,
            'status' => 'approved',
            'reviewer_id' => $reviewer,
            'comment' => $comment,
            'decided_at' => now(),
        ]);

        $template->update([
            'approval_level' => $level,
            'lifecycle' => $level === 'provider' ? 'submitted' : 'approved',
        ]);

        if ($level === 'provider') {
            return $this->submitToProvider($store, $template);
        }

        return $template;
    }

    protected function submitToProvider(Store $store, Template $template): Template
    {
        $provider = $this->whatsapp->for($store);

        $issues = $this->compliance->check($template->body, ['category' => $template->category]);
        if ($this->compliance->hasBlockingErrors($issues)) {
            $template->update(['compliance_issues' => $issues, 'status' => 'draft']);
            return $template;
        }

        try {
            $result = $provider->createTemplate([
                'name' => $template->name,
                'language' => $template->language,
                'category' => $template->category,
                'components' => [
                    ['type' => 'BODY', 'text' => $template->body],
                ],
            ]);

            $template->update([
                'provider_template_id' => data_get($result, 'id', $result['data']['id'] ?? null),
                'status' => 'submitted',
                'lifecycle' => 'in_review',
                'approval_level' => 'provider',
            ]);

            TemplateApproval::create([
                'store_id' => $store->id,
                'template_id' => $template->id,
                'level' => 'provider',
                'status' => 'pending',
                'provider_status' => 'in_review',
            ]);
        } catch (\Throwable $e) {
            $template->update(['status' => 'draft', 'lifecycle' => 'draft']);
            throw $e;
        }

        return $template;
    }
}
