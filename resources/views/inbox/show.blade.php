@extends('layouts.app')

@section('title', 'Conversation')

@section('head')
    <script>window.StoreId = "{{ $store->id }}";</script>
    @vite(['resources/js/inbox.js'])
@endsection

@section('content')
    <div class="row">
        <h1>{{ $conversation->contact?->profile_name ?: $conversation->contact?->wa_id }}</h1>
        <span class="badge {{ $conversation->agent_mode }}">{{ $conversation->agent_mode }}</span>
    </div>

    <div class="inbox-layout">
        <div class="thread" id="thread"
             data-conversation="{{ $conversation->id }}"
             data-messages-url="{{ route('api.conversations.messages', $conversation) }}"
             data-last-message-id="{{ $conversation->messages->last()?->id }}">
            @foreach ($conversation->messages as $message)
                <div class="msg {{ $message->direction }}">
                    <div class="bubble">{{ $message->body }}</div>
                    <small>{{ $message->created_at->diffForHumans() }}</small>
                </div>
            @endforeach
        </div>

        <div class="side">
            <h3>Customer</h3>
            @if ($conversation->contact?->shopifyCustomer)
                <dl>
                    <dt>Orders</dt><dd>{{ $conversation->contact->shopifyCustomer->total_orders }}</dd>
                    <dt>LTV</dt><dd>{{ number_format($conversation->contact->shopifyCustomer->lifetime_value, 2) }}</dd>
                    <dt>Email</dt><dd>{{ $conversation->contact->shopifyCustomer->email }}</dd>
                </dl>
            @else
                <p class="muted">No linked Shopify customer.</p>
            @endif

            <h3>Actions</h3>
            <div class="stack">
                @if ($conversation->agent_mode === 'auto')
                    <form method="POST" action="{{ route('inbox.takeover', $conversation) }}">
                        @csrf
                        <button class="btn primary">Take over from AI</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('inbox.return-to-ai', $conversation) }}">
                        @csrf
                        <button class="btn">Return to AI</button>
                    </form>
                @endif

                <form method="POST" action="{{ route('inbox.assign', $conversation) }}" class="row">
                    @csrf
                    <select name="agent_id">
                        <option value="">Unassigned</option>
                        @foreach ($agents as $agent)<option value="{{ $agent->id }}">{{ $agent->name }}</option>@endforeach
                    </select>
                    <button class="btn">Assign</button>
                </form>

                <button class="btn" id="suggest-btn" data-url="{{ route('inbox.suggest', $conversation) }}">AI-suggest reply</button>
            </div>

            <form method="POST" action="{{ route('inbox.send', $conversation) }}" class="stack">
                @csrf
                <textarea name="body" id="reply-input" rows="3" placeholder="Write a reply…"></textarea>
                <button class="btn primary" type="submit">Send</button>
            </form>
        </div>
    </div>
@endsection
