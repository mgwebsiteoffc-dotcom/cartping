@extends('layouts.app')

@section('title', 'Campaign — ' . $campaign->name)

@section('content')
    <div class="row">
        <h1>{{ $campaign->name }}</h1>
        <div class="row">
            <span class="badge {{ $campaign->status === 'completed' ? '' : 'warning' }}">{{ $campaign->status }}</span>
            @if (in_array($campaign->status, ['draft', 'scheduled', 'sending']))
                <form method="POST" action="{{ route('campaigns.cancel', $campaign) }}">
                    @csrf
                    <button class="btn">Cancel</button>
                </form>
            @endif
        </div>
    </div>

    <div class="cards">
        <div class="stat"><span>Recipients</span><strong>{{ $campaign->total_recipients }}</strong></div>
        <div class="stat"><span>Sent</span><strong>{{ $campaign->sent_count }}</strong></div>
        <div class="stat"><span>Delivered</span><strong>{{ $campaign->delivered_count }}</strong></div>
        <div class="stat"><span>Read</span><strong>{{ $campaign->read_count }}</strong></div>
        <div class="stat"><span>Failed</span><strong>{{ $campaign->failed_count }}</strong></div>
    </div>

    @if ($campaign->schedule_at)
        <p class="muted">Scheduled for {{ $campaign->schedule_at }}</p>
    @endif

    <table class="table">
        <thead><tr><th>Contact</th><th>Status</th><th>Sent</th><th>Delivered</th><th>Read</th></tr></thead>
        <tbody>
        @forelse ($campaign->deliveries as $d)
            <tr>
                <td>{{ $d->contact?->profile_name ?: ($d->contact?->wa_id ?: '—') }}</td>
                <td><span class="badge">{{ $d->status }}</span></td>
                <td>{{ $d->sent_at?->diffForHumans() ?? '—' }}</td>
                <td>{{ $d->delivered_at?->diffForHumans() ?? '—' }}</td>
                <td>{{ $d->read_at?->diffForHumans() ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="5">No deliveries.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
