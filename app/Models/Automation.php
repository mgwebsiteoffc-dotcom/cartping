<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A configurable automation rule. Each rule maps a trigger -> optional delay ->
 * an action (send template / start agent flow).
 *
 * trigger: order_created | order_fulfilled | order_shipped | order_cancelled |
 *          abandoned_checkout | abandoned_browse | welcome
 * delay_after: minutes to wait (0 = immediate) — evaluated by the scheduler.
 */
class Automation extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'store_id',
        'name',
        'trigger',
        'delay_after_minutes',
        'template_id',
        'agent_flow',          // optional: name of agent flow to trigger
        'conditions',          // JSON conditions on the event payload
        'is_active',
        'send_count',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'delay_after_minutes' => 'integer',
            'conditions' => 'array',
            'is_active' => 'boolean',
            'send_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function dueAt(): ?\Illuminate\Support\Carbon
    {
        return now()->addMinutes($this->delay_after_minutes ?? 0);
    }
}
