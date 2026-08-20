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
            <select name="whatsapp_provider">
                <option value="meta">Meta Cloud API</option>
                <option value="whatify">Whatify</option>
            </select>
        </label>
        <label>Provider token / API key<input name="whatsapp_token" value="{{ old('whatsapp_token') }}" required></label>
        <label>Phone number ID (Meta)<input name="phone_number_id" value="{{ old('phone_number_id') }}"></label>

        <button class="btn primary" type="submit">Connect</button>
    </form>
@endsection
