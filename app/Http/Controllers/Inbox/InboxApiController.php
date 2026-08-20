<?php

namespace App\Http\Controllers\Inbox;

use App\Http\Controllers\Controller;
use App\Models\Conversation;

class InboxApiController extends Controller
{
    public function list()
    {
        $store = request()->user('store');

        return response()->json(
            Conversation::where('store_id', $store->id)
                ->with(['contact:id,wa_id,profile_name', 'assignee:id,name'])
                ->orderBy('last_message_at', 'desc')
                ->get()
        );
    }

    public function messages(Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        return response()->json($conversation->messages()->orderBy('created_at')->get());
    }
}
