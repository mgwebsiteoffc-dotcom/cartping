@extends('owner.layout')

@section('title', 'Users')

@section('content')
    <h1>Users</h1>

    <section class="card">
        <h2>Create user</h2>
        <form method="POST" action="{{ route('owner.users.store') }}" class="stack">
            @csrf
            <label>Name<input name="name" required></label>
            <label>Email<input type="email" name="email" required></label>
            <label>Password<input type="password" name="password" min="8" required></label>
            <label>Role
                <select name="role">
                    <option value="superadmin">Superadmin</option>
                    <option value="admin">Admin</option>
                    <option value="owner">Store owner</option>
                    <option value="agent">Agent</option>
                    <option value="viewer">Viewer</option>
                </select>
            </label>
            <label>Store (for store roles)
                <select name="store_id">
                    <option value="">Platform-level</option>
                    @foreach ($stores as $s)
                        <option value="{{ $s->id }}">{{ $s->myshopify_domain }}</option>
                    @endforeach
                </select>
            </label>
            <button class="btn primary" type="submit">Create user</button>
        </form>
    </section>

    <table class="table">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Store</th><th>Active</th></tr></thead>
        <tbody>
        @forelse ($users as $u)
            <tr>
                <td>{{ $u->name }}</td>
                <td>{{ $u->email }}</td>
                <td>{{ $u->role }}</td>
                <td>{{ $u->store?->myshopify_domain ?? 'Platform' }}</td>
                <td><span class="badge {{ $u->active ? '' : 'warning' }}">{{ $u->active ? 'Active' : 'Inactive' }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5">No users.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $users->links() }}
@endsection
