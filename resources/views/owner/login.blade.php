@extends('layouts.guest')

@section('title', 'Owner login — CartPing')

@section('content')
    <h1>🛠️ CartPing Owner</h1>
    <p class="muted">Sign in with a platform (superadmin/admin) account.</p>
    <form method="POST" action="{{ route('owner.login') }}" class="stack">
        @csrf
        <label>Email<input type="email" name="email" value="{{ old('email') }}" required></label>
        <label>Password<input type="password" name="password" required></label>
        <label><input type="checkbox" name="remember" value="1"> Remember me</label>
        <button class="btn primary" type="submit">Sign in</button>
    </form>
@endsection
