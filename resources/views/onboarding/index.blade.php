@extends('layouts.app')

@section('title', 'Onboarding')

@section('content')
    <h1>Set up {{ $store->name }}</h1>

    <ol class="steps">
        <li class="{{ $store->onboarding_step >= 1 ? 'done' : '' }}">Shopify</li>
        <li class="{{ $store->onboarding_step >= 2 ? 'done' : '' }}">WhatsApp</li>
        <li class="{{ $store->onboarding_step >= 3 ? 'done' : '' }}">AI Agent</li>
        <li class="{{ $store->onboarding_step >= 4 ? 'done' : '' }}">Widget</li>
        <li class="{{ $store->onboarding_step >= 5 ? 'done' : '' }}">Template</li>
        <li class="{{ $store->onboarding_step >= 6 ? 'done' : '' }}">Test</li>
    </ol>

    @if ($store->onboarding_step <= 2)
        <section class="card">
            <h2>1 · Connect Shopify</h2>

            <p><strong>Recommended — connect in real time via Shopify OAuth.</strong><br>
            Enter your store URL and you'll be redirected to Shopify to approve the app.
            We exchange the token instantly and bring you right back.</p>

            @if (config('shopify.api_key') && config('shopify.api_secret'))
                <form method="GET" action="{{ route('auth.shopify') }}" class="stack">
                    <label>Store URL<input name="shop" placeholder="yourshop.myshopify.com" value="{{ $store->myshopify_domain }}"></label>
                    <button class="btn primary" type="submit">Connect with Shopify (OAuth)</button>
                </form>
            @else
                <p class="muted">Public app OAuth isn't configured (no SHOPIFY_API_KEY/SECRET). Use a Custom App token below, or add the keys to your <code>.env</code>.</p>
            @endif

            <hr>

            <p><strong>Alternative — Custom App access token.</strong><br>
            Create a Custom App in your Shopify admin, generate an Admin API token and paste it here.</p>
            <form method="POST" action="{{ route('onboarding.shopify') }}" class="stack">
                @csrf
                <label>Shopify Admin API token<input name="shopify_access_token" placeholder="shpat_..." value="{{ $store->access_token ?? '' }}"></label>
                <button class="btn" type="submit">Save &amp; sync products</button>
            </form>
        </section>
    @endif

    @if ($store->onboarding_step >= 2 && $store->onboarding_step <= 3)
        <section class="card">
            <h2>2 · Connect WhatsApp</h2>
            <form method="POST" action="{{ route('onboarding.whatsapp') }}" class="stack" id="whatsapp-form">
                @csrf
                <label>Provider
                    <select name="provider" id="provider-select">
                        <option value="meta" @selected(($store->whatsappConnection?->provider ?? 'meta') === 'meta')>Meta Cloud API</option>
                        <option value="whatify" @selected(($store->whatsappConnection?->provider ?? '') === 'whatify')>Whatify</option>
                    </select>
                </label>

                <div class="provider-fields" data-provider="meta">
                    <label>Meta access token<input name="token" placeholder="EAAG..." value="{{ $store->whatsappConnection?->token ?? '' }}"></label>
                    <label>Phone number ID<input name="phone_number_id" placeholder="1000..." value="{{ $store->whatsappConnection?->phone_number_id ?? '' }}"></label>
                    <label>WABA ID (Business Account)<input name="waba_id" value="{{ $store->whatsappConnection?->waba_id ?? '' }}"></label>
                </div>

                <div class="provider-fields" data-provider="whatify">
                    <label>Whatify API key<input name="api_key" placeholder="wfy_..." value="{{ $store->whatsappConnection?->api_key ?? '' }}"></label>
                    <label>Whatify API secret (optional)<input name="api_secret" value="{{ $store->whatsappConnection?->api_secret ?? '' }}"></label>
                    <p class="muted">Generate these from your <a href="https://whatify.in" target="_blank" rel="noopener">Whatify dashboard</a> → API credentials.</p>
                </div>

                <button class="btn primary" type="submit">Connect</button>
            </form>

            @if ($store->whatsappConnection?->is_connected)
                <form method="POST" action="{{ route('onboarding.whatsapp.test') }}" class="stack" style="margin-top:1rem">
                    @csrf
                    <label>Test number<input name="test_number" placeholder="+15551234567" required></label>
                    <button class="btn" type="submit">Send test message</button>
                </form>
            @endif
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
    @endif

    @if ($store->onboarding_step >= 3 && $store->onboarding_step <= 4)
        <section class="card">
            <h2>3 · Configure the AI Agent</h2>
            <form method="POST" action="{{ route('onboarding.agent') }}" class="stack">
                @csrf
                <label>Agent name<input name="name" value="{{ $store->agentConfig?->name ?? 'Store Assistant' }}"></label>
                <label>Persona<textarea name="persona" rows="3">{{ $store->agentConfig?->persona }}</textarea></label>
                <label>Autonomous mode<input type="checkbox" name="autonomous" value="1" @checked($store->agentConfig?->autonomous ?? true)></label>
                <button class="btn primary" type="submit">Save agent</button>
            </form>
        </section>
    @endif

    @if ($store->onboarding_step >= 4 && $store->onboarding_step <= 5)
        <section class="card">
            <h2>4 · Install the Widget</h2>
            <form method="POST" action="{{ route('onboarding.widget') }}" class="stack">
                @csrf
                <label>Widget type
                    <select name="type">
                        <option value="simple_button">Simple button</option>
                        <option value="tooltip">Tooltip</option>
                        <option value="chat_widget" selected>Chat widget</option>
                        <option value="smart_contextual">Smart contextual</option>
                    </select>
                </label>
                <button class="btn primary" type="submit">Install widget</button>
            </form>
            <p class="muted">Script: <code>&lt;script src="{{ url('js/widget.js') }}" data-shop="{{ $store->myshopify_domain }}" async&gt;&lt;/script&gt;</code></p>
        </section>
    @endif

    @if ($store->onboarding_step >= 5 && $store->onboarding_step < 6)
        <section class="card">
            <h2>5 · Create your first template</h2>
            <form method="POST" action="{{ route('onboarding.template') }}" class="stack">
                @csrf
                <label>Describe the message<textarea name="brief" rows="3" placeholder="e.g. send order confirmation with tracking info"></textarea></label>
                <button class="btn primary" type="submit">Generate with AI</button>
            </form>
        </section>
    @endif
@endsection
