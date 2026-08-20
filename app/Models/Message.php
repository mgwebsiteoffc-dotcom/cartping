<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single message in a conversation.
 * direction: inbound | outbound
 * role: contact | agent | assistant | system
 * kind: text | image | audio | document | video | interactive | template | location | sticker
 */
class Message extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'store_id',
        'conversation_id',
        'contact_id',
        'direction',
        'role',
        'kind',
        'body',
        'media_url',
        'mime_type',
        'provider_message_id',   // wa_id of the provider
        'template_id',
        'ai_meta',               // tool calls, confidence, suggested reply used
        'status',                // queued | sent | delivered | read | failed
        'delivered_at',
        'read_at',
        'sent_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'ai_meta' => 'array',
            'metadata' => 'array',
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
