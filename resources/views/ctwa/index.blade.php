@extends('layouts.app')

@section('title', 'CTWA Ads')

@section('content')
    <h1>Click-to-WhatsApp Ads</h1>

    <section class="card">
        <h2>New CTWA campaign</h2>
        <form method="POST" action="{{ route('ctwa.store') }}" class="stack">
            @csrf
            <label>Campaign name<input name="name" required></label>
            <label>Meta campaign ID<input name="meta_campaign_id"></label>
            <label>Destination WhatsApp number<input name="destination_wa_number" placeholder="+15551234567"></label>
            <label>Template ID (CTWA)<input name="ctwa_template_id"></label>
            <label>Active<input type="checkbox" name="is_active" value="1" checked></label>
            <button class="btn primary" type="submit">Create ad</button>
        </form>
    </section>

    <table class="table">
        <thead><tr><th>Name</th><th>Tracking URL</th><th>Status</th></tr></thead>
        <tbody>
        @forelse ($ads as $ad)
            <tr>
                <td>{{ $ad->name }}</td>
                <td><code>{{ $ad->tracking_url }}</code></td>
                <td>{{ $ad->is_active ? 'active' : 'paused' }}</td>
            </tr>
        @empty
            <tr><td colspan="3">No CTWA ads yet.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
