@extends('layouts.app')

@section('title', 'WhatsApp settings')

@section('content')
    <h1>WhatsApp connection</h1>

    <section class="card">
        <p class="muted">Status: {{ $connection?->is_connected ? '✅ Connected' : '⚠️ Not connected' }}</p>
        <form method="POST" action="{{ route('settings.whatsapp.update') }}" class="stack">
            @csrf
            <label>Provider
                <select name="provider">
                    <option value="meta" @selected(($connection?->provider ?? 'meta') === 'meta')>Meta Cloud API</option>
                    <option value="whatify" @selected(($connection?->provider ?? '') === 'whatify')>Whatify</option>
                </select>
            </label>
            <label>Token / API key<input name="token" value="{{ $connection?->token ?? '' }}"></label>
            <label>Phone number ID<input name="phone_number_id" value="{{ $connection?->phone_number_id ?? '' }}"></label>
            <label>WABA ID (Meta)<input name="waba_id" value="{{ $connection?->waba_id ?? '' }}"></label>
            <button class="btn primary" type="submit">Save &amp; test</button>
        </form>
    </section>
@endsection
