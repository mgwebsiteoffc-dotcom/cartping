@extends('owner.layout')

@section('title', 'SaaS dashboard')

@section('content')
    <h1>Platform overview</h1>

    <div class="cards">
        <div class="stat"><span>Total stores</span><strong>{{ $totalStores }}</strong></div>
        <div class="stat"><span>Active stores</span><strong>{{ $activeStores }}</strong></div>
        <div class="stat"><span>Contacts</span><strong>{{ $totalContacts }}</strong></div>
        <div class="stat"><span>Messages</span><strong>{{ $totalMessages }}</strong></div>
        <div class="stat"><span>Users</span><strong>{{ $totalUsers }}</strong></div>
        <div class="stat"><span>Paid stores</span><strong>{{ $paidCount }}</strong></div>
        <div class="stat"><span>Est. MRR</span><strong>{{ number_format($totalRevenueEstimate, 2) }}</strong></div>
    </div>

    <section class="card">
        <h2>Revenue by plan</h2>
        <table class="table">
            <thead><tr><th>Plan</th><th>Stores</th><th>MRR</th></tr></thead>
            <tbody>
            @forelse ($revenueByPlan as $code => $data)
                <tr>
                    <td>{{ ucfirst($code) }}</td>
                    <td>{{ $data['count'] }}</td>
                    <td>{{ number_format($data['mrr'], 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="3">No paid subscriptions yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2>Recent stores</h2>
        <table class="table">
            <thead><tr><th>Store</th><th>Plan</th><th>Status</th><th>Created</th></tr></thead>
            <tbody>
            @forelse ($recentStores as $s)
                <tr>
                    <td>{{ $s->myshopify_domain }}</td>
                    <td>{{ $s->plan?->name ?? '—' }}</td>
                    <td><span class="badge {{ $s->isDisabled() ? 'error' : '' }}">{{ $s->isDisabled() ? 'Disabled' : 'Active' }}</span></td>
                    <td>{{ $s->created_at?->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="4">No stores yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection
