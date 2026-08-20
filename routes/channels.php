<?php

use App\Models\Conversation;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Reverb broadcast channels
|--------------------------------------------------------------------------
| The inbox subscribes to private-conversation.{id} and
| private-store.{id} for real-time updates and agent takeovers.
*/

Broadcast::channel('store.{storeId}', function ($user, string $storeId) {
    return (string) ($user->store_id ?? $user->id) === $storeId;
});

Broadcast::channel('conversation.{conversationId}', function ($user, string $conversationId) {
    $conversation = Conversation::find($conversationId);

    if (! $conversation) {
        return false;
    }

    // Staff agent of the owning store, or the WhatsApp contact channel.
    return (string) ($user->store_id ?? $user->id) === $conversation->store_id;
});
