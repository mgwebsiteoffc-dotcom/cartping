<?php

namespace App\Http\Controllers\Inbox;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\Label;
use App\Services\Ai\AgentOrchestrator;
use App\Services\Whatsapp\WhatsappSender;
use Illuminate\Http\Request;

/**
 * Merchant-facing real-time chat inbox. Human agents view conversations,
 * take over from the AI, assign, label, and send. Updates stream over Reverb.
 */
class InboxController extends Controller
{
    public function __construct(
        protected WhatsappSender $sender,
        protected AgentOrchestrator $agent,
    ) {
    }

    public function index()
    {
        $store = request()->user('store');

        $conversations = Conversation::where('store_id', $store->id)
            ->with(['contact', 'assignee'])
            ->orderByRaw('last_message_at is null, last_message_at desc')
            ->get();

        return view('inbox.index', [
            'store' => $store,
            'conversations' => $conversations,
            'labels' => Label::where('store_id', $store->id)->get(),
            'agents' => $store->users ?? collect(),
        ]);
    }

    public function show(Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $store = $conversation->store;

        return view('inbox.show', [
            'store' => $store,
            'conversation' => $conversation->load(['contact.shopifyCustomer', 'messages', 'labels', 'assignee']),
            'agents' => $store->users ?? collect(),
            'labels' => Label::where('store_id', $store->id)->get(),
        ]);
    }

    public function takeover(Conversation $conversation)
    {
        $this->authorize('update', $conversation);

        $conversation->takeover(request()->user('store'), 'Agent manually took over');

        return back();
    }

    public function returnToAi(Conversation $conversation)
    {
        $this->authorize('update', $conversation);
        $conversation->returnToAi(request()->user('store'));

        return back();
    }

    public function assign(Conversation $conversation, Request $request)
    {
        $this->authorize('update', $conversation);

        $conversation->update(['assignee_id' => $request->input('agent_id') ?: null]);

        return back();
    }

    public function attachLabels(Conversation $conversation, Request $request)
    {
        $this->authorize('update', $conversation);

        $conversation->labels()->sync($request->input('label_ids', []));

        return back();
    }

    public function send(Conversation $conversation, Request $request)
    {
        $this->authorize('update', $conversation);

        $data = $request->validate(['body' => ['required', 'string']]);
        $store = request()->user('store');

        $this->sender->text($store, $conversation->contact, $data['body'], $conversation);

        return back();
    }

    /**
     * AI-suggested reply for the agent to review/send.
     */
    public function suggest(Conversation $conversation)
    {
        $this->authorize('view', $conversation);

        $last = $conversation->messages()->where('direction', 'inbound')->latest()->first();
        $store = request()->user('store');

        $response = $this->agent->respond(
            $store,
            $last?->body ?? 'Customer waiting',
            $conversation->contact,
            $conversation,
            $conversation->messages()->limit(20)->get()->map(fn ($m) => [
                'role' => $m->direction === 'outbound' ? 'assistant' : 'user',
                'content' => $m->body,
            ])->all()
        );

        return response()->json(['suggestion' => $response['content'] ?? null]);
    }
}
