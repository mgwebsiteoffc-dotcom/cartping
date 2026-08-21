@extends('layouts.guest')

@section('title', 'Store disabled')

@section('content')
    <h1>Store disabled</h1>
    <p class="lede">Your store account has been disabled by the CartPing platform.</p>
    <p class="muted">If you believe this is a mistake, please contact support. Billing and account issues can be resolved through your Shopify admin.</p>
    <form method="POST" action="{{ route('auth.logout') }}">
        @csrf
        <button class="btn" type="submit">Sign out</button>
    </form>
@endsection
