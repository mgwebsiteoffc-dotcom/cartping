@extends('layouts.app')

@section('title', 'Templates')

@section('content')
    <h1>Message Templates</h1>

    <div class="layout">
        <section class="card">
            <h2>Generate with AI</h2>
            <form method="POST" action="{{ route('templates.generate') }}" class="stack">
                @csrf
                <label>Brief<textarea name="brief" rows="3" placeholder="e.g. abandoned cart recovery with a 10% discount link"></textarea></label>
                <button class="btn primary" type="submit">Generate + A/B variants</button>
            </form>
        </section>

        <section class="card">
            <h2>Create manually</h2>
            <form method="POST" action="{{ route('templates.store') }}" class="stack">
                @csrf
                <label>Name<input name="name" placeholder="order_update" required></label>
                <label>Display name<input name="display_name" placeholder="Order Update"></label>
                <label>Category
                    <select name="category">
                        <option value="MARKETING">Marketing</option>
                        <option value="UTILITY">Utility</option>
                        <option value="AUTHENTICATION">Authentication</option>
                    </select>
                </label>
                <label>Language<input name="language" value="en"></label>
                <label>Body<textarea name="body" rows="3" placeholder="Hi {{1}}, your order {{2}} has shipped. Track here: {{3}}" required></textarea></label>
                <button class="btn primary" type="submit">Create template</button>
            </form>
        </section>
    </div>

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
                    @if ($template->lifecycle === 'dashboard_required')
                        <span class="badge warning">Create in {{ $store->whatsappConnection?->provider === 'whatify' ? 'Whatify' : 'provider' }} dashboard</span>
                    @elseif ($template->approval_level === 'internal' && $template->status === 'draft')
                        <form method="POST" action="{{ route('templates.approve', $template) }}">
                            @csrf
                            <input type="hidden" name="level" value="compliance">
                            <button class="btn" title="Compliance check">Compliance check</button>
                        </form>
                    @elseif ($template->approval_level === 'compliance')
                        <form method="POST" action="{{ route('templates.submit', $template) }}">
                            @csrf
                            <button class="btn primary" title="Submit to provider">Submit to provider</button>
                        </form>
                    @elseif ($template->approval_level === 'provider' || $template->status === 'submitted')
                        <span class="badge">In review</span>
                    @else
                        <form method="POST" action="{{ route('templates.approve', $template) }}">
                            @csrf
                            <input type="hidden" name="level" value="internal">
                            <button class="btn">Start review</button>
                        </form>
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
            <tr><td colspan="6">No templates yet. Generate with AI or create manually above.</td></tr>
        @endforelse
        </tbody>
    </table>
@endsection
