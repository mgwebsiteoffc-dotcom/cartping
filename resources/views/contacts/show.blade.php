@extends('layouts.app')

@section('title', 'Contact — ' . ($contact->profile_name ?: $contact->wa_id))

@section('content')
    <div class="row">
        <h1>{{ $contact->profile_name ?: $contact->wa_id }}</h1>
        <a class="btn" href="{{ route('contacts.index') }}">← Contacts</a>
    </div>

    <div class="layout">
        <section class="card">
            <h2>Details</h2>
            <dl class="side">
                <dt>Number</dt><dd>{{ $contact->wa_id }}</dd>
                <dt>Email</dt><dd>{{ $contact->email ?? '—' }}</dd>
                <dt>Consent</dt><dd>{{ $contact->consent_state }} @if($contact->opt_in_source)(via {{ $contact->opt_in_source }})@endif</dd>
                <dt>First seen</dt><dd>{{ $contact->first_seen_at?->diffForHumans() ?? '—' }}</dd>
                <dt>Last seen</dt><dd>{{ $contact->last_seen_at?->diffForHumans() ?? '—' }}</dd>
            </dl>
        </section>

        <section class="card">
            <h2>Shopify customer</h2>
            @if ($contact->shopifyCustomer)
                <dl class="side">
                    <dt>Orders</dt><dd>{{ $contact->shopifyCustomer->total_orders }}</dd>
                    <dt>LTV</dt><dd>{{ number_format($contact->shopifyCustomer->lifetime_value, 2) }}</dd>
                    <dt>Last order</dt><dd>{{ $contact->shopifyCustomer->last_order_at?->diffForHumans() ?? '—' }}</dd>
                </dl>
            @else
                <p class="muted">No linked Shopify customer.</p>
            @endif
        </section>

        <section class="card">
            <h2>Tags</h2>
            <form method="POST" action="{{ route('contacts.tags', $contact) }}" class="stack">
                @csrf
                <label>Tags (comma separated)<input name="tags" value="{{ implode(',', $contact->tags ?? []) }}" placeholder="vip, repeat"></label>
                <button class="btn" type="submit">Save tags</button>
            </form>
        </section>

        <section class="card">
            <h2>Recent conversations</h2>
            @forelse ($contact->conversations as $conv)
                <p class="muted">
                    {{ $conv->status }} · {{ $conv->last_message_at?->diffForHumans() ?? 'no messages' }}
                    — <a href="{{ route('inbox.show', $conv) }}">open</a>
                </p>
            @empty
                <p class="muted">No conversations.</p>
            @endforelse
        </section>
    </div>
@endsection
