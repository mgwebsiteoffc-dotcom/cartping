@extends('layouts.guest')

@section('title', 'Connect manually — CartPing')

@section('content')
    <h1>Connect your Custom App</h1>
    <p class="muted">Create a Custom App in your Shopify admin, generate an Admin API access token, then choose your WhatsApp provider and paste credentials.</p>
    <form method="POST" action="{{ route('auth.manual') }}" class="stack">
        @csrf
        <label>Shop name<input name="name" value="{{ old('name') }}" required></label>
        <label>Shop domain<input name="myshopify_domain" placeholder="yourshop.myshopify.com" value="{{ old('myshopify_domain') }}" required></label>
        <label>Contact email<input type="email" name="contact_email" value="{{ old('contact_email') }}" required></label>
        <label>Password<input type="password" name="password" required></label>
        <label>Shopify Admin API access token<input name="shopify_access_token" value="{{ old('shopify_access_token') }}" required></label>

        <label>WhatsApp provider
            <select name="whatsapp_provider" id="provider-select">
                <option value="meta">Meta Cloud API</option>
                <option value="whatify">Whatify</option>
            </select>
        </label>

        <div class="provider-fields" data-provider="meta">
            <label>Meta access token<input name="whatsapp_token" value="{{ old('whatsapp_token') }}"></label>
            <label>Phone number ID (Meta)<input name="phone_number_id" value="{{ old('phone_number_id') }}"></label>
        </div>

        <div class="provider-fields" data-provider="whatify">
            <label>Whatify API key<input name="whatsapp_api_key" value="{{ old('whatsapp_api_key') }}"></label>
            <label>Whatify API secret (optional)<input name="whatsapp_api_secret" value="{{ old('whatsapp_api_secret') }}"></label>
        </div>

        <button class="btn primary" type="submit">Connect</button>
    </form>

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
