@extends('owner.layout')

@section('title', 'Plans')

@section('content')
    <h1>Plans</h1>

    <section class="card">
        <h2>New plan</h2>
        <form method="POST" action="{{ route('owner.plans.store') }}" class="stack">
            @csrf
            <label>Name<input name="name" placeholder="Pro" required></label>
            <label>Code<input name="code" placeholder="pro" required></label>
            <label>Price / month<input type="number" step="0.01" name="price_monthly" value="0"></label>
            <label>Price / year<input type="number" step="0.01" name="price_yearly" value="0"></label>
            <label>Limits (JSON)<textarea name="limits" rows="2">{"conversations": 500, "broadcasts": 1000}</textarea></label>
            <label>Features (JSON)<textarea name="features" rows="2">{"flows": true, "campaigns": true}</textarea></label>
            <button class="btn primary" type="submit">Create plan</button>
        </form>
    </section>

    <table class="table">
        <thead><tr><th>Name</th><th>Code</th><th>Monthly</th><th>Yearly</th><th>Active</th></tr></thead>
        <tbody>
        @forelse ($plans as $plan)
            <tr>
                <td>{{ $plan->name }}</td>
                <td>{{ $plan->code }}</td>
                <td>{{ number_format($plan->price_monthly, 2) }}</td>
                <td>{{ number_format($plan->price_yearly, 2) }}</td>
                <td><span class="badge {{ $plan->is_active ? '' : 'warning' }}">{{ $plan->is_active ? 'Active' : 'Hidden' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5">No plans yet.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
