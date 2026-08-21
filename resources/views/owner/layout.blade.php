<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Owner') · CartPing SaaS</title>
    @vite(['resources/css/app.css'])
</head>
<body class="app">
<div class="shell">
    <aside class="sidebar">
        <div class="brand">🛠️ CartPing Owner</div>
        <nav>
            <a href="{{ route('owner.dashboard') }}" class="{{ request()->routeIs('owner.dashboard') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('owner.stores') }}" class="{{ request()->routeIs('owner.stores') ? 'active' : '' }}">Stores</a>
            <a href="{{ route('owner.plans') }}" class="{{ request()->routeIs('owner.plans') ? 'active' : '' }}">Plans</a>
            <a href="{{ route('owner.users') }}" class="{{ request()->routeIs('owner.users') ? 'active' : '' }}">Users</a>
        </nav>
        <form method="POST" action="{{ route('owner.logout') }}">
            @csrf
            <button class="logout">Sign out</button>
        </form>
    </aside>
    <main class="content">
        @if (session('status'))
            <div class="flash success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="flash error"><ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>
        @endif
        @yield('content')
    </main>
</div>
</body>
</html>
