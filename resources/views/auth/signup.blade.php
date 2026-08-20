@extends('layouts.guest')

@section('title', 'Sign up — CartPing')

@section('content')
    <h1>Create your account</h1>
    <form method="POST" action="{{ route('auth.signup') }}" class="stack">
        @csrf
        <label>Shop name<input name="name" value="{{ old('name') }}" required></label>
        <label>Shop domain<input name="myshopify_domain" placeholder="yourshop.myshopify.com" value="{{ old('myshopify_domain') }}" required></label>
        <label>Contact email<input type="email" name="contact_email" value="{{ old('contact_email') }}" required></label>
        <label>Password<input type="password" name="password" required></label>
        <label>Confirm password<input type="password" name="password_confirmation" required></label>
        <button class="btn primary" type="submit">Create account</button>
    </form>
    <p class="muted">Prefer the Shopify App? <a href="{{ route('auth.shopify') }}">Install via Shopify OAuth</a>.</p>
@endsection
