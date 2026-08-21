@extends('layouts.app')

@section('title', 'Flow builder')

@section('content')
    <div class="row">
        <h1>Automation flows</h1>
        <details class="new-flow">
            <summary class="btn primary">+ New flow</summary>
            <form method="POST" action="{{ route('flows.create') }}" class="stack card" style="margin-top:.5rem">
                @csrf
                <label>Name<input name="name" placeholder="Welcome flow" required></label>
                <label>Trigger
                    <select name="trigger">
                        <option value="welcome">New contact (welcome)</option>
                        <option value="new_message">Any new message</option>
                        <option value="keyword">Keyword</option>
                    </select>
                </label>
                <label>Trigger value<input name="trigger_value" placeholder="e.g. START or a keyword (for keyword trigger)"></label>
                <button class="btn primary" type="submit">Create &amp; open builder</button>
            </form>
        </details>
    </div>

    <table class="table">
        <thead><tr><th>Name</th><th>Trigger</th><th>Status</th><th>Runs</th><th>Completions</th><th></th></tr></thead>
        <tbody>
        @forelse ($flows as $flow)
            <tr>
                <td>{{ $flow->name }}</td>
                <td>{{ $flow->trigger }}@if($flow->trigger_value) ({{ $flow->trigger_value }})@endif</td>
                <td><span class="badge {{ $flow->is_active ? '' : 'warning' }}">{{ $flow->is_active ? 'Active' : 'Draft' }}</span></td>
                <td>{{ $flow->runs_count }}</td>
                <td>{{ $flow->completions_count }}</td>
                <td class="row">
                    <a class="btn" href="{{ route('flows.builder', $flow) }}">Edit</a>
                    <a class="btn" href="{{ route('flows.runs', $flow) }}">Runs</a>
                </td>
            </tr>
        @empty
            <tr><td colspan="6">No flows yet. Create one to build a visual WhatsApp automation.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
