@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="row">
        <h1>Dashboard</h1>
        @if (! $onboardingStep >= 6)
            <a class="btn" href="{{ route('onboarding.index') }}">Continue setup →</a>
        @endif
    </div>

    <div class="cards">
        <div class="stat"><span>Open conversations</span><strong>{{ $conversationsOpen }}</strong></div>
        <div class="stat"><span>Orders today</span><strong>{{ $ordersToday }}</strong></div>
        <div class="stat"><span>Messages sent (30d)</span><strong>{{ $metrics['messages']['sent'] }}</strong></div>
        <div class="stat"><span>Attributed revenue (30d)</span><strong>{{ number_format($metrics['revenue']['attributed'], 2) }} {{ $store->currency }}</strong></div>
    </div>

    <div class="cards">
        <div class="stat"><span>AI resolution rate</span><strong>{{ $metrics['ai']['resolution_rate'] }}%</strong></div>
        <div class="stat"><span>Escalations</span><strong>{{ $metrics['ai']['escalations'] }}</strong></div>
        <div class="stat"><span>CTWA clicks</span><strong>{{ $metrics['ctwa']['clicks'] }}</strong></div>
        <div class="stat"><span>Widget CTR</span><strong>{{ $metrics['widget']['ctr'] }}%</strong></div>
    </div>

    <section class="card">
        <h2>Next steps</h2>
        <ul>
            <li><a href="{{ route('inbox.index') }}">Open the inbox</a> to monitor conversations</li>
            <li><a href="{{ route('templates.index') }}">Create &amp; approve message templates</a></li>
            <li><a href="{{ route('ctwa.index') }}">Set up a Click-to-WhatsApp campaign</a></li>
            <li><a href="{{ route('analytics.index') }}">Explore full analytics</a></li>
        </ul>
    </section>
@endsection
