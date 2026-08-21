@extends('owner.layout')

@section('title', 'Stores')

@section('content')
    <h1>Stores</h1>
    <table class="table">
        <thead><tr><th>Store</th><th>Contacts</th><th>Plan</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        @forelse ($stores as $s)
            <tr>
                <td>{{ $s->myshopify_domain }}</td>
                <td>{{ $s->contacts_count }}</td>
                <td>{{ $s->plan?->name ?? '—' }}</td>
                <td><span class="badge {{ $s->isDisabled() ? 'error' : '' }}">{{ $s->isDisabled() ? 'Disabled' : 'Active' }}</span></td>
                <td>
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
                            <label><input type="checkbox" name="disabled" value="1" @checked($s->isDisabled())> Disabled</label>
                            <button class="btn primary" type="submit">Save</button>
                        </form>
                    </details>
                </td>
            </tr>
        @empty
            <tr><td colspan="5">No stores yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $stores->links() }}
@endsection
