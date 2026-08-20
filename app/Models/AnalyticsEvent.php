<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Unified raw analytics event store. Everything funnels in here (message
 * performance, automation conversions, template A/B sends, CTWA clicks, widget
 * interactions, AI resolutions, escalations, revenue attribution). Rolled up
 * hourly by the metrics service into dashboard aggregates.
 */
class AnalyticsEvent extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'store_id',
        'event',             // message.sent, automation.converted, template.sent,
                             // ctwa.click, widget.click, ai.resolved, agent.escalated,
                             // revenue.attributed, popup.shown, ...
        'category',          // message | automation | template | ctwa | widget | ai | revenue | consent
        'contact_id',
        'conversation_id',
        'template_id',
        'ad_id',
        'automation_id',
        'session_id',        // widget session / browser session id
        'value',             // monetary or score value
        'channel',
        'occurred_at',
        'payload',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'occurred_at' => 'datetime',
            'payload' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public static function record(string $event, array $context = []): void
    {
        $occurredAt = $context['occurred_at'] ?? now();
        $category = $context['category'] ?? explode('.', $event)[0];

        static::create([
            'store_id' => $context['store_id'],
            'event' => $event,
            'category' => $category,
            'contact_id' => $context['contact_id'] ?? null,
            'conversation_id' => $context['conversation_id'] ?? null,
            'template_id' => $context['template_id'] ?? null,
            'ad_id' => $context['ad_id'] ?? null,
            'automation_id' => $context['automation_id'] ?? null,
            'session_id' => $context['session_id'] ?? null,
            'value' => $context['value'] ?? null,
            'channel' => $context['channel'] ?? 'wa',
            'occurred_at' => $occurredAt,
            'payload' => $context['payload'] ?? [],
        ]);
    }
}
