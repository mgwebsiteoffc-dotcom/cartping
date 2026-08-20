<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A 1:1 WhatsApp thread with a contact. Has a lifecycle state that reflects
 * whether the AI agent is in control, a human has taken over, or the thread
 * is in a post-conversation state.
 *
 * status: open | pending_human | assigned | resolved | closed
 * agent_mode: auto | human_takeover | manual
 */
class Conversation extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'store_id',
        'contact_id',
        'assignee_id',         // User id (human agent)
        'channel',             // wa | instagram | web (future)
        'status',
        'agent_mode',          // auto | human_takeover | manual
        'ai_confidence',       // last agent confidence 0..1
        'escalated_reason',
        'source',              // widget | ctwa | inbound | template
        'shopify_order_id',
        'ctwa_click_id',
        'last_message_at',
        'resolved_at',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'ai_confidence' => 'float',
            'last_message_at' => 'datetime',
            'resolved_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class)->orderBy('created_at');
    }

    public function labels(): BelongsToMany
    {
        return $this->belongsToMany(Label::class)->withTimestamps();
    }

    public function isHumanControlled(): bool
    {
        return $this->agent_mode === 'human_takeover' || $this->agent_mode === 'manual';
    }

    public function takeover(User $agent, ?string $reason = null): void
    {
        $this->update([
            'assignee_id' => $agent->id,
            'agent_mode' => 'human_takeover',
            'status' => 'assigned',
            'escalated_reason' => $reason ?: $this->escalated_reason,
        ]);
    }

    public function returnToAi(User $agent): void
    {
        $this->update(['agent_mode' => 'auto', 'status' => 'open']);
    }
}
