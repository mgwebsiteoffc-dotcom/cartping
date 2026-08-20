@extends('layouts.app')

@section('title', 'Setup complete')

@section('content')
    <div class="card centered">
        <h1>🎉 You're all set, {{ $store->name }}!</h1>
        <p>Your WhatsApp automation is configured. Send yourself a test message and explore the dashboard.</p>
        <form method="POST" action="{{ route('onboarding.test-message') }}" class="stack">
            @csrf
            <label>Your WhatsApp number<input name="test_number" placeholder="+15551234567" required></label>
            <button class="btn primary" type="submit">Send me a test message</button>
        </form>
        <a class="btn" href="{{ route('dashboard.index') }}">Go to dashboard</a>
    </div>
@endsection
