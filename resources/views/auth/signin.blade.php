@extends('layouts.guest')

@section('title', 'Sign in — CartPing')

@section('content')
    <h1>Sign in</h1>

    {{-- If we reached this page from inside the Shopify admin, the session token
         (id_token/session query param) can authenticate us without a password. --}}
    @if (config('shopify.embedded', true))
        <div id="shopify-auth-hint" style="display:none" class="muted">Checking Shopify session…</div>
        <script>
            (function () {
                var qs = new URLSearchParams(window.location.search);
                var token = qs.get('id_token') || qs.get('session');
                if (!token) return;
                var hint = document.getElementById('shopify-auth-hint');
                if (hint) hint.style.display = 'block';
                fetch("{{ route('auth.shopify.session') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                               'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
                    body: JSON.stringify({ session_token: token })
                }).then(function (r) { return r.json(); }).then(function (d) {
                    if (d.ok) { window.location.href = d.dashboard; }
                }).catch(function () {});
            })();
        </script>
    @endif

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
