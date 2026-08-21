@extends('owner.layout')

@section('title', 'Stores')

@section('content')
    <h1>Stores</h1>
    <table class="table">
        <thead><tr><th>Store</th><th>Contacts</th><th>Plan</th><th>Charge</th><th>Status</th><th>Actions</th></tr>
        <tbody>
        @forelse ($stores as $s)
            <tr>
                <td>{{ $s->myshopify_domain }}</td>
                <td>{{ $s->contacts_count }}</td>
                <td>{{ $s->plan?->name ?? '—' }}</td>
                <td>
                    @if ($s->shopifyChargeId())
                        <span class="badge">charge #{{ $s->shopifyChargeId() }}</span>
                    @else
                        <span class="muted">{{ $s->billingPlanCode() === 'free' ? 'Free (no charge)' : '—' }}</span>
                    @endif
                </td>
                <td><span class="badge {{ $s->isDisabled() ? 'error' : '' }}">{{ $s->isDisabled() ? 'Disabled' : 'Active' }}</span></td>
                <td>
                    <div class="row">
                        <form method="POST" action="{{ route('owner.stores.toggle', $s) }}">
                            @csrf
                            <button class="btn {{ $s->isDisabled() ? 'primary' : '' }}">{{ $s->isDisabled() ? 'Enable' : 'Disable' }}</button>
                        </form>
                        @if ($s->shopifyChargeId())
                            <form method="POST" action="{{ route('owner.stores.verify-charge', $s) }}">
                                @csrf
                                <button class="btn" title="Verify real charge status from Shopify">Verify charge</button>
                            </form>
                        @endif
                        <details>
                            <summary class="btn">Manage</summary>
                            <form method="POST" action="{{ route('owner.stores.update', $s) }}" class="stack card" style="margin-top:.4rem;min-width:260px">
                                @csrf
                                <label>Plan
                                    <select name="plan_id">
                                        <option value="">—</option>
                                        @foreach ($plans as $p)
                                            <option value="{{ $p->id }}" @selected($s->plan_id === $p->id)>{{ $p->name }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label>Plan expiry<input type="date" name="plan_expires_at" value="{{ $s->plan_expires_at?->format('Y-m-d') }}"></label>
                                <button class="btn primary" type="submit">Save plan</button>
                            </form>
                        </details>
                    </div>
                </td>
            </tr>
        @empty
            <tr><td colspan="5">No stores yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $stores->links() }}
@endsection
