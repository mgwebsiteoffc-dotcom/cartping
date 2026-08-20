<?php

namespace App\Services\Ai\Tools;

use App\Models\Contact;
use App\Models\Conversation;
use App\Models\Store;

/**
 * Runtime context handed to every tool execution.
 */
final class ToolContext
{
    public function __construct(
        public Store $store,
        public ?Contact $contact = null,
        public ?Conversation $conversation = null,
        public array $extra = [],
    ) {
    }
}
