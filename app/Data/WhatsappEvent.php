<?php

namespace App\Data;

use Illuminate\Support\Carbon;

/**
 * Uniform inbound WhatsApp event after provider normalization.
 *
 * type: message | message_status | optin | optout | ctwa_lead | conversation_started
 */
final class WhatsappEvent
{
    public function __construct(
        public string $provider,
        public string $type,
        public ?string $waId,
        public ?string $profileName,
        public ?string $from,              // sender number (alias of waId for some providers)
        public ?string $direction,
        public ?string $kind,              // text | image | interactive | ...
        public ?string $body,
        public ?string $mediaUrl,
        public ?string $mediaMimeType,
        public ?string $messageId,         // provider message id
        public ?string $replyToMessageId,
        public ?string $contextWaId,
        public ?Carbon $timestamp,
        public array $raw = [],
    ) {
    }

    public static function fromProvider(string $provider, string $type, array $attributes = []): self
    {
        return new self(
            provider: $provider,
            type: $type,
            waId: $attributes['wa_id'] ?? $attributes['from'] ?? null,
            profileName: $attributes['profile_name'] ?? null,
            from: $attributes['from'] ?? null,
            direction: $attributes['direction'] ?? 'inbound',
            kind: $attributes['kind'] ?? 'text',
            body: $attributes['body'] ?? null,
            mediaUrl: $attributes['media_url'] ?? null,
            mediaMimeType: $attributes['media_mime_type'] ?? null,
            messageId: $attributes['message_id'] ?? null,
            replyToMessageId: $attributes['reply_to_message_id'] ?? null,
            contextWaId: $attributes['context_wa_id'] ?? null,
            timestamp: isset($attributes['timestamp']) ? Carbon::parse($attributes['timestamp']) : null,
            raw: $attributes['raw'] ?? [],
        );
    }

    public function isText(): bool
    {
        return $this->kind === 'text';
    }

    public function hasMedia(): bool
    {
        return in_array($this->kind, ['image', 'audio', 'document', 'video', 'sticker'], true);
    }
}
