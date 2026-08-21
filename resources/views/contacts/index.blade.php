@extends('layouts.app')

@section('title', 'Contacts')

@section('content')
    <div class="row">
        <h1>Contacts</h1>
        <form method="GET" class="row">
            <input name="q" placeholder="Search name / number / email" value="{{ request('q') }}">
            <button class="btn" type="submit">Search</button>
        </form>
    </div>

    <div class="row" style="margin: 12px 0">
        <a class="btn" href="{{ route('contacts.index') }}">All</a>
        @foreach ($allTags as $tag)
            <a class="btn {{ request('tag') === $tag ? 'primary' : '' }}" href="{{ route('contacts.index', ['tag' => $tag]) }}">#{{ $tag }}</a>
        @endforeach
    </div>

    <table class="table">
        <thead><tr><th>Contact</th><th>Number</th><th>Consent</th><th>Tags</th><th>Last seen</th><th></th></tr></thead>
        <tbody>
        @forelse ($contacts as $c)
            <tr>
                <td>{{ $c->profile_name ?: '—' }}</td>
                <td>{{ $c->wa_id }}</td>
                <td><span class="badge {{ $c->hasOptedIn() ? '' : 'warning' }}">{{ $c->consent_state }}</span></td>
                <td>
                    @foreach ($c->tags ?? [] as $t)
                        <span class="badge">#{{ $t }}</span>
                    @endforeach
                </td>
                <td>{{ $c->last_seen_at?->diffForHumans() ?? '—' }}</td>
                <td><a class="btn" href="{{ route('contacts.show', $c) }}">View</a></td>
            </tr>
        @empty
            <tr><td colspan="6">No contacts yet. Inbound WhatsApp messages create contacts automatically.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $contacts->links() }}
@endsection
