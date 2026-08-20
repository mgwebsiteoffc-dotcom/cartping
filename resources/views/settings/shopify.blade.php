@extends('layouts.app')

@section('title', 'Shopify connection')

@section('content')
    <div class="row">
        <h1>Shopify connection</h1>
        <a class="btn" href="{{ route('settings.whatsapp') }}">WhatsApp settings →</a>
    </div>

    <section class="card">
        <h2>Connection status</h2>

        @if ($connection?->access_token)
            <dl class="side">
                <dt>Mode</dt><dd>{{ $connection->mode === 'oauth' ? 'Public app (OAuth)' : 'Custom App (manual token)' }}</dd>
                <dt>Store</dt><dd>{{ $shop['name'] ?? $store->myshopify_domain }} <small>({{ $store->myshopify_domain }})</small></dd>
                <dt>Scopes</dt><dd>{{ $connection->scope }}</dd>

                @if ($connection->mode === 'oauth')
                    <dt>Token type</dt>
                    <dd>
                        @if ($connection->refresh_token)
                            <span class="badge">Expiring offline token</span> (auto-refreshed)
                        @else
                            <span class="badge warning">Legacy offline token</span>
                        @endif
                    </dd>
                    @if ($connection->expires_at)
                        <dt>Access token expires</dt><dd>{{ $connection->expires_at->diffForHumans() }} <small>({{ $connection->expires_at->format('M j, Y H:i') }})</small></dd>
                    @endif
                    @if ($connection->refresh_token_expires_at)
                        <dt>Refresh token expires</dt><dd>{{ $connection->refresh_token_expires_at->diffForHumans() }}</dd>
                    @endif
                @endif
            </dl>

            @if ($shop)
                <p class="muted">Connected to the Shopify Admin API (v{{ config('shopify.api_version') }}).</p>
            @else
                <div class="flash error">Could not reach the Shopify Admin API with the stored token — it may be expired or revoked.</div>
            @endif
        @else
            <p class="muted">No Shopify connection yet.</p>
        @endif
    </section>

    <section class="card">
        <h2>Manage token</h2>

        @if ($connection?->mode === 'oauth' && $oauthConfigured)
            <p>Re-authorize to refresh scopes or a revoked token. Shopify will redirect you back automatically.</p>
            <form method="GET" action="{{ route('auth.shopify') }}" class="stack">
                <input type="hidden" name="shop" value="{{ $store->myshopify_domain }}">
                <button class="btn primary" type="submit">Reconnect with Shopify (OAuth)</button>
            </form>
        @elseif ($connection?->mode === 'manual' || ! $connection)
            <p>Custom App mode uses a pasted Admin API token. Paste a new one to rotate it.</p>
            <form method="POST" action="{{ route('onboarding.shopify') }}" class="stack">
                @csrf
                <label>Shopify Admin API token<input name="shopify_access_token" placeholder="shpat_..."></label>
                <button class="btn" type="submit">Save token</button>
            </form>
        @else
            <p class="muted">OAuth isn't configured (no SHOPIFY_API_KEY/SECRET). Add them to your <code>.env</code> to reconnect via OAuth.</p>
        @endif
    </section>
@endsection
