@extends('layouts.app')

@section('title', 'Templates')

@section('content')
    <div class="row">
        <h1>Message Templates</h1>
        <form method="POST" action="{{ route('templates.sync') }}">
            @csrf
            <button class="btn primary" type="submit">↻ Sync status from provider</button>
        </form>
    </div>

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
                    <button class="btn" data-preview="{{ $template->id }}">Preview</button>
                    <button class="btn" data-header="{{ $template->id }}">Header</button>
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

    {{-- Preview modal --}}
    <div id="preview-modal" class="modal">
        <div class="modal-box">
            <div class="row"><h3>Preview</h3><button class="btn modal-close">✕</button></div>
            <div class="wa-preview" id="wa-preview-body">
                <div id="wa-preview-header" class="wa-preview-header"></div>
                <div id="wa-preview-text" class="wa-preview-text"></div>
            </div>
            <p class="muted">Placeholders {{ '{{1}}' }} {{ '{{2}}' }} shown as-is. Final values are filled at send time.</p>
        </div>
    </div>

    {{-- Header modal --}}
    <div id="header-modal" class="modal">
        <div class="modal-box">
            <div class="row"><h3>Template header</h3><button class="btn modal-close">✕</button></div>
            <form method="POST" id="header-form" class="stack">
                @csrf
                <input type="hidden" name="template_id" id="header-template-id">
                <label>Header type
                    <select name="header_type" id="header-type">
                        <option value="text">Text</option>
                        <option value="image">Image</option>
                        <option value="document">Document</option>
                        <option value="video">Video</option>
                    </select>
                </label>
                <label>Header text (for text type)<input name="header_text" id="header-text" placeholder="e.g. Special offer inside!"></label>
                <label>Media URL (for image/document/video)<input name="header_url" id="header-url" placeholder="https://.../image.jpg"></label>
                <button class="btn primary" type="submit">Save header</button>
            </form>
        </div>
    </div>

    <script>
        (function () {
            var templates = @json($templates->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->display_name ?: $t->name,
                'body' => $t->body,
                'header' => $t->header,
            ]));

            function tmpl(id) { return templates.find(function (t) { return t.id === id; }) || {}; }
            function esc(s) { return (s||'').replace(/[&<>"']/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }

            var pm = document.getElementById('preview-modal');
            var hm = document.getElementById('header-modal');

            document.querySelectorAll('[data-preview]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var t = tmpl(Number(b.getAttribute('data-preview')));
                    var h = t.header || {};
                    document.getElementById('wa-preview-header').innerHTML = h.media_url
                        ? '<img src="' + esc(h.media_url) + '" alt="" style="max-width:100%;max-height:140px;border-radius:8px">'
                        : (h.text ? '<strong>' + esc(h.text) + '</strong>' : '');
                    document.getElementById('wa-preview-text').textContent = t.body || '';
                    pm.style.display = 'flex';
                });
            });

            document.querySelectorAll('[data-header]').forEach(function (b) {
                b.addEventListener('click', function () {
                    var id = b.getAttribute('data-header');
                    document.getElementById('header-template-id').value = id;
                    document.getElementById('header-form').setAttribute('action',
                        '{{ route('templates.header', '__ID__') }}'.replace('__ID__', id));
                    hm.style.display = 'flex';
                });
            });

            document.querySelectorAll('.modal-close').forEach(function (c) {
                c.addEventListener('click', function () { pm.style.display = 'none'; hm.style.display = 'none'; });
            });
            window.addEventListener('click', function (e) {
                if (e.target === pm || e.target === hm) { pm.style.display = 'none'; hm.style.display = 'none'; }
            });
        })();
    </script>
@endsection
