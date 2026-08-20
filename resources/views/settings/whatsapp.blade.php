@extends('layouts.app')

@section('title', 'WhatsApp settings')

@section('content')
    <h1>WhatsApp connection</h1>

    <section class="card">
        <p class="muted">Status: {{ $connection?->is_connected ? '✅ Connected' : '⚠️ Not connected' }}</p>

        <form method="POST" action="{{ route('settings.whatsapp.update') }}" class="stack" id="whatsapp-form">
            @csrf

            <label>Provider
                <select name="provider" id="provider-select">
                    <option value="meta" @selected(($connection?->provider ?? 'meta') === 'meta')>Meta Cloud API</option>
                    <option value="whatify" @selected(($connection?->provider ?? '') === 'whatify')>Whatify</option>
                </select>
            </label>

            <div class="provider-fields" data-provider="meta">
                <label>Meta access token<input name="token" placeholder="EAAG..." value="{{ $connection?->token ?? '' }}"></label>
                <label>Phone number ID<input name="phone_number_id" placeholder="1000..." value="{{ $connection?->phone_number_id ?? '' }}"></label>
                <label>WABA ID (Business Account)<input name="waba_id" value="{{ $connection?->waba_id ?? '' }}"></label>
            </div>

            <div class="provider-fields" data-provider="whatify">
                <label>Whatify API key<input name="api_key" placeholder="wfy_..." value="{{ $connection?->api_key ?? '' }}"></label>
                <label>Whatify API secret (optional)<input name="api_secret" value="{{ $connection?->api_secret ?? '' }}"></label>
                <p class="muted">Generate these from your <a href="https://whatify.in" target="_blank" rel="noopener">Whatify dashboard</a> → API credentials.</p>
            </div>

            <button class="btn primary" type="submit">Save &amp; test</button>
        </form>
    </section>

    <script>
        (function () {
            var sel = document.getElementById('provider-select');
            var blocks = document.querySelectorAll('.provider-fields');
            function toggle() {
                blocks.forEach(function (b) {
                    b.style.display = (b.getAttribute('data-provider') === sel.value) ? 'block' : 'none';
                });
            }
            if (sel) { sel.addEventListener('change', toggle); toggle(); }
        })();
    </script>
@endsection
