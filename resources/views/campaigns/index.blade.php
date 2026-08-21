@extends('layouts.app')

@section('title', 'Broadcast campaigns')

@section('content')
    <div class="row">
        <h1>Broadcast campaigns</h1>
        <a class="btn" href="{{ route('campaigns.calendar') }}">📅 Schedule calendar</a>
    </div>
    <p class="muted">{{ $contactsCount }} opted-in contact(s) eligible for marketing broadcasts.</p>

    <section class="card" id="new">
        <h2>New campaign</h2>
        <form method="POST" action="{{ route('campaigns.store') }}" class="stack">
            @csrf
            <label>Campaign name<input name="name" placeholder="Diwali sale" required></label>

            <label>Template
                <select name="template_id">
                    <option value="">No template (use message body)</option>
                    @foreach ($templates as $t)
                        <option value="{{ $t->id }}">{{ $t->display_name ?: $t->name }} ({{ $t->status }})</option>
                    @endforeach
                </select>
            </label>

            <label>Message body (if no template)<textarea name="message_body" rows="2" placeholder="Hi {{1}}, here's an exclusive offer for you!"></textarea></label>

            <label>Audience
                <select name="audience_type" id="audience-type">
                    <option value="all">All opted-in contacts</option>
                    <option value="segment">By segment</option>
                    <option value="tag">By tag</option>
                    <option value="manual">Manual numbers</option>
                </select>
            </label>
            <div id="audience-segment" style="display:none">
                <label>Segment
                    <select name="audience_segment_id">
                        @foreach ($segments as $s)
                            <option value="{{ $s->id }}">{{ $s->name }} ({{ $s->contact_count }})</option>
                        @endforeach
                    </select>
                </label>
            </div>
            <div id="audience-tag" style="display:none">
                <label>Tag<input name="audience_tag" placeholder="vip"></label>
            </div>
            <div id="audience-manual" style="display:none">
                <label>Phone numbers (comma separated)<input name="audience_numbers" placeholder="+919876543210, +15551234567"></label>
            </div>

            <label>Schedule (empty = send now)<input type="datetime-local" name="schedule_at"></label>
            <label>Send limit per hour<input type="number" name="send_limit_per_hour" value="500" min="1" max="10000"></label>

            <button class="btn primary" type="submit">Create &amp; send / schedule</button>
        </form>
    </section>

    <script>
        (function () {
            var sel = document.getElementById('audience-type');
            function toggle() {
                document.getElementById('audience-tag').style.display = sel.value === 'tag' ? 'block' : 'none';
                document.getElementById('audience-manual').style.display = sel.value === 'manual' ? 'block' : 'none';
                document.getElementById('audience-segment').style.display = sel.value === 'segment' ? 'block' : 'none';
            }
            sel.addEventListener('change', toggle); toggle();
        })();
    </script>

    <table class="table">
        <thead><tr><th>Name</th><th>Status</th><th>Recipients</th><th>Sent</th><th>Delivered</th><th>Read</th><th>Failed</th><th></th></tr></thead>
        <tbody>
        @forelse ($campaigns as $c)
            <tr>
                <td><a href="{{ route('campaigns.show', $c) }}">{{ $c->name }}</a></td>
                <td><span class="badge {{ $c->status === 'completed' ? '' : 'warning' }}">{{ $c->status }}</span></td>
                <td>{{ $c->total_recipients }}</td>
                <td>{{ $c->sent_count }}</td>
                <td>{{ $c->delivered_count }}</td>
                <td>{{ $c->read_count }}</td>
                <td>{{ $c->failed_count }}</td>
                <td>
                    @if (in_array($c->status, ['draft', 'scheduled', 'sending']))
                        <form method="POST" action="{{ route('campaigns.cancel', $c) }}">
                            @csrf
                            <button class="btn">Cancel</button>
                        </form>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="8">No campaigns yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    {{ $campaigns->links() }}
@endsection
