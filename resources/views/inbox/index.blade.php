@extends('layouts.app')

@section('title', 'Inbox')

@section('content')
    <div class="row">
        <h1>Inbox</h1>
        <a class="btn" href="{{ route('inbox.index') }}">Refresh</a>
    </div>

    <table class="table">
        <thead>
        <tr>
            <th>Contact</th>
            <th>Status</th>
            <th>Mode</th>
            <th>Source</th>
            <th>Last message</th>
        </tr>
        </thead>
        <tbody>
        @forelse ($conversations as $conversation)
            <tr>
                <td>
                    <a href="{{ route('inbox.show', $conversation) }}">
                        {{ $conversation->contact?->profile_name ?: ($conversation->contact?->wa_id ?: 'Unknown') }}
                    </a>
                </td>
                <td>{{ $conversation->status }}</td>
                <td>{{ $conversation->agent_mode }}</td>
                <td>{{ $conversation->source }}</td>
                <td>{{ $conversation->last_message_at?->diffForHumans() }}</td>
            </tr>
        @empty
            <tr><td colspan="5">No conversations yet. Inbound WhatsApp messages will appear here in real time.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
