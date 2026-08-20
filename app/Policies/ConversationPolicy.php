<?php

namespace App\Policies;

use App\Models\Conversation;
use App\Models\Store;

class ConversationPolicy
{
    public function view(Store $store, Conversation $conversation): bool
    {
        return $conversation->store_id === $store->id;
    }

    public function update(Store $store, Conversation $conversation): bool
    {
        return $this->view($store, $conversation);
    }
}
