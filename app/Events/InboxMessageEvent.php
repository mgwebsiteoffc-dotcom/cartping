<?php

namespace App\Events;

use App\Models\Conversation;
use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcasts a new message to the merchant's real-time inbox over Reverb.
 */
class InboxMessageEvent implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(
        public Conversation $conversation,
        public ?Message $message = null,
        public ?array $system = null,
    ) {
    }

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('store.'.$this->conversation->store_id),
            new PrivateChannel('conversation.'.$this->conversation->id),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'conversation_id' => $this->conversation->id,
            'message' => $this->message?->only(['id', 'body', 'direction', 'kind', 'created_at']),
            'system' => $this->system,
            'contact_id' => $this->conversation->contact_id,
        ];
    }
}
