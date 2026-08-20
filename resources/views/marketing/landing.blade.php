@extends('layouts.guest')

@section('title', 'CartPing — WhatsApp Automation for Shopify')

@section('content')
    <h1>🛒 CartPing</h1>
    <p class="lede">WhatsApp automation for Shopify merchants.</p>
    <ul class="features">
        <li>⚡ Automated order &amp; shipping notifications</li>
        <li>💬 AI conversational store agent (GPT-4o via OpenRouter)</li>
        <li>📦 Abandoned cart &amp; browse recovery with configurable delays</li>
        <li>🧠 AI template generation with approvals &amp; A/B testing</li>
        <li>📈 Click-to-WhatsApp ads with full ROAS attribution</li>
        <li>🎯 Smart contextual WhatsApp widget + entry/exit popups</li>
        <li>🔴 Real-time human handoff inbox (WebSockets)</li>
    </ul>
    <div class="actions">
        <a class="btn primary" href="{{ route('auth.signup') }}">Sign up</a>
        <a class="btn" href="{{ route('auth.signin') }}">Sign in</a>
        <a class="btn" href="{{ route('auth.shopify') }}">Install via Shopify</a>
    </div>
@endsection
