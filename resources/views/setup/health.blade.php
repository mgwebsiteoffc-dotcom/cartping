@extends('layouts.app')

@section('title', 'Setup health')

@section('content')
    <div class="row">
        <h1>Setup health</h1>
        <span class="badge {{ $okCount === $totalCount ? '' : 'warning' }}">{{ $okCount }} / {{ $totalCount }} complete</span>
    </div>

    <p class="muted">A quick status of what's configured for {{ $store->myshopify_domain }}. Red items are the likely cause of issues.</p>

    <div class="health-list">
        @foreach ($items as $item)
            <div class="health-item {{ $item['ok'] ? 'ok' : 'missing' }}">
                <div class="health-icon">{{ $item['ok'] ? '✅' : '⚠️' }}</div>
                <div class="health-body">
                    <strong>{{ $item['label'] }}</strong>
                    <span class="muted">{{ $item['hint'] }}</span>
                </div>
                @if (! $item['ok'] && $item['action'])
                    <a class="btn" href="{{ route($item['action']) }}">Fix</a>
                @endif
            </div>
        @endforeach
    </div>

    <section class="card">
        <h2>Shared-hosting reminders</h2>
        <ul>
            <li>Queue cron: <code>* * * * * /usr/bin/php /path/artisan queue:work --once</code></li>
            <li>WhatsApp provider webhook must point to your app for delivered/read counts.</li>
            <li>Real-time inbox (WebSockets) needs a VPS; shared hosting uses polling.</li>
        </ul>
    </section>
@endsection
