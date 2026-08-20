@extends('layouts.app')

@section('title', 'Templates')

@section('content')
    <h1>Message Templates</h1>

    <section class="card">
        <h2>Generate with AI</h2>
        <form method="POST" action="{{ route('templates.generate') }}" class="stack">
            @csrf
            <label>Brief<textarea name="brief" rows="3" placeholder="e.g. abandoned cart recovery with a 10% discount link"></textarea></label>
            <button class="btn primary" type="submit">Generate template + A/B variants</button>
        </form>
    </section>

    <table class="table">
        <thead>
        <tr><th>Name</th><th>Category</th><th>Status</th><th>Approval</th><th>A/B</th><th>Actions</th></tr>
        </thead>
        <tbody>
        @forelse ($templates as $template)
            <tr>
                <td>{{ $template->display_name ?: $template->name }}</td>
                <td>{{ $template->category }}</td>
                <td>{{ $template->status }}</td>
                <td>{{ $template->approval_level }}</td>
                <td>{{ $template->variants->count() }} variants</td>
                <td class="row">
                    @if ($template->status === 'draft')
                        <form method="POST" action="{{ route('templates.approve', $template) }}">
                            @csrf
                            <input type="hidden" name="level" value="internal">
                            <button class="btn" title="Internal review">Review</button>
                        </form>
                        <form method="POST" action="{{ route('templates.approve', $template) }}">
                            @csrf
                            <input type="hidden" name="level" value="compliance">
                            <button class="btn" title="Compliance check">Compliance</button>
                        </form>
                        <form method="POST" action="{{ route('templates.submit', $template) }}">
                            @csrf
                            <button class="btn primary" title="Submit to provider">Submit</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('templates.ab', $template) }}">
                            @csrf
                            <button class="btn">Add A/B</button>
                        </form>
                    @endif
                </td>
            </tr>
            @if ($template->compliance_issues)
                <tr>
                    <td colspan="6">
                        <small>
                            @foreach ($template->compliance_issues as $issue)
                                <span class="badge {{ $issue['severity'] }}">{{ $issue['message'] }}</span>
                            @endforeach
                        </small>
                    </td>
                </tr>
            @endif
        @empty
            <tr><td colspan="6">No templates yet. Generate your first one above.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
