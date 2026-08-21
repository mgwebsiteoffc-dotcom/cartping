@extends('layouts.app')

@section('title', 'Flow runs — ' . $flow->name)

@section('content')
    <div class="row">
        <h1>Runs — {{ $flow->name }}</h1>
        <a class="btn" href="{{ route('flows.builder', $flow) }}">Back to builder</a>
    </div>

    <table class="table">
        <thead><tr><th>Contact</th><th>State</th><th>Started</th><th>Finished</th><th>Steps</th></tr></thead>
        <tbody>
        @forelse ($runs as $run)
            <tr>
                <td>{{ $run->contact?->profile_name ?: ($run->contact?->wa_id ?: '—') }}</td>
                <td><span class="badge">{{ $run->state }}</span></td>
                <td>{{ $run->started_at?->diffForHumans() }}</td>
                <td>{{ $run->finished_at?->diffForHumans() ?? '—' }}</td>
                <td>{{ count($run->steps ?? []) }}</td>
            </tr>
        @empty
            <tr><td colspan="5">No runs yet. Activate the flow and test it.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $runs->links() }}
@endsection
