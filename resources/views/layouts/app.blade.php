<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'CartPing') · {{ $store->name ?? 'Merchant' }}</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css'])
    @yield('head')
    @if (config('shopify.embedded', true) && config('shopify.api_key'))
        {{-- Embedded Shopify app: expose the App Bridge global so embedded pages
             can use the admin SDK (toast, navigation, redirect). --}}
        <script>
            window.shopify = window.shopify || {};
            window.shopify.shop = "{{ $store->myshopify_domain ?? '' }}";
            window.shopify.apiKey = "{{ config('shopify.api_key') }}";
        </script>
        <script src="https://cdn.shopify.com/shopifycloud/shopify-app-bridge.js"></script>
    @endif
</head>
<body class="app">
<div class="shell">
    <aside class="sidebar">
        <div class="brand">🛒 CartPing</div>
        <div class="shop-name">{{ $store->name ?? '' }}</div>
        <nav>
            <a href="{{ route('dashboard.index') }}" class="{{ request()->routeIs('dashboard*') ? 'active' : '' }}">Dashboard</a>
            <a href="{{ route('inbox.index') }}" class="{{ request()->routeIs('inbox*') ? 'active' : '' }}">Inbox</a>
            <a href="{{ route('templates.index') }}" class="{{ request()->routeIs('templates*') ? 'active' : '' }}">Templates</a>
            <a href="{{ route('ctwa.index') }}" class="{{ request()->routeIs('ctwa*') ? 'active' : '' }}">CTWA Ads</a>
            <a href="{{ route('analytics.index') }}" class="{{ request()->routeIs('analytics*') ? 'active' : '' }}">Analytics</a>
            <a href="{{ route('agent.index') }}" class="{{ request()->routeIs('agent*') ? 'active' : '' }}">AI Agent</a>
            <a href="{{ route('widget.index') }}" class="{{ request()->routeIs('widget*') ? 'active' : '' }}">Widget</a>
            <a href="{{ route('products.index') }}" class="{{ request()->routeIs('products*') ? 'active' : '' }}">Products</a>
            <a href="{{ route('flows.index') }}" class="{{ request()->routeIs('flows*') ? 'active' : '' }}">Flows</a>
            <a href="{{ route('campaigns.index') }}" class="{{ request()->routeIs('campaigns*') ? 'active' : '' }}">Campaigns</a>
            <a href="{{ route('contacts.index') }}" class="{{ request()->routeIs('contacts*') ? 'active' : '' }}">Contacts</a>
            <a href="{{ route('settings.shopify') }}" class="{{ request()->routeIs('settings.shopify') ? 'active' : '' }}">Shopify</a>
            <a href="{{ route('settings.whatsapp') }}" class="{{ request()->routeIs('settings.whatsapp') ? 'active' : '' }}">WhatsApp</a>
        </nav>
        <form method="POST" action="{{ route('auth.logout') }}">
            @csrf
            <button class="logout">Sign out</button>
        </form>
    </aside>
    <main class="content">
        @if (session('status'))
            <div class="flash success">{{ session('status') }}</div>
        @endif
        @if ($errors->any())
            <div class="flash error">
                <ul>@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif
        @yield('content')
    </main>
</div>
@stack('scripts')
</body>
</html>
