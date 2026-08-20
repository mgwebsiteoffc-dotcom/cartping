@extends('layouts.guest')

@section('title', 'Sign in — CartPing')

@section('content')
    <h1>Sign in</h1>
    <form method="POST" action="{{ route('auth.signin') }}" class="stack">
        @csrf
        <label>Shop domain
            <input name="myshopify_domain" placeholder="yourshop.myshopify.com" value="{{ old('myshopify_domain') }}" required>
        </label>
        <label>Password
            <input type="password" name="password" required>
        </label>
        <button class="btn primary" type="submit">Sign in</button>
    </form>
    <p class="muted">No account? <a href="{{ route('auth.signup') }}">Sign up</a> or
        <a href="{{ route('auth.manual') }}">connect via Custom App</a>.</p>
@endsection
