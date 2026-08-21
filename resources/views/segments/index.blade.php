@extends('layouts.app')

@section('title', 'Segments')

@section('content')
    <h1>Contact segments</h1>
    <p class="muted">Build reusable audience segments from conditions. Segments can be used to target broadcast campaigns.</p>

    <section class="card">
        <h2>New segment</h2>
        <form method="POST" action="{{ route('segments.store') }}" class="stack" id="segment-form">
            @csrf
            <label>Name<input name="name" placeholder="VIP customers" required></label>
            <label>Description<input name="description" placeholder="High-value repeat customers"></label>

            <input type="hidden" name="conditions" id="conditions-input">

            <div id="segment-builder" class="segment-builder">
                <div class="segment-group" data-group="0">
                    <div class="segment-group-title">Group 1 <button type="button" class="btn btn-sm add-condition">+ condition</button></div>
                </div>
            </div>

            <div class="row">
                <span id="segment-count" class="muted">0 matching contacts</span>
                <button type="button" class="btn" id="preview-btn">Preview</button>
                <button class="btn primary" type="submit">Create segment</button>
            </div>
        </form>
    </section>

    <table class="table">
        <thead><tr><th>Name</th><th>Description</th><th>Contacts</th><th>Status</th><th></th></tr></thead>
        <tbody>
        @forelse ($segments as $segment)
            <tr>
                <td>{{ $segment->name }}</td>
                <td>{{ $segment->description }}</td>
                <td>{{ $segment->contact_count }}</td>
                <td><span class="badge {{ $segment->is_active ? '' : 'warning' }}">{{ $segment->is_active ? 'Active' : 'Disabled' }}</span></td>
                <td class="row">
                    <form method="POST" action="{{ route('segments.refresh', $segment) }}">
                        @csrf
                        <button class="btn">Recount</button>
                    </form>
                    <form method="POST" action="{{ route('segments.delete', $segment) }}">
                        @csrf
                        <button class="btn">Delete</button>
                    </form>
                </td>
            </tr>
        @empty
            <tr><td colspan="5">No segments yet.</td></tr>
        @endforelse
        </tbody>
    </table>

    <script>
        (function () {
            var builder = document.getElementById('segment-builder');
            var countEl = document.getElementById('segment-count');
            var previewBtn = document.getElementById('preview-btn');
            var conditionsInput = document.getElementById('conditions-input');
            var FIELDS = [
                ['tags', 'Tags'], ['consent', 'Consent'], ['opted_in', 'Opted in'],
                ['profile_name', 'Name'], ['email', 'Email'], ['wa_id', 'Number'],
                ['source', 'Source'], ['last_seen_days', 'Last seen (days ago)'],
                ['total_orders', 'Total orders'], ['lifetime_value', 'Lifetime value'],
            ];
            var OPS = ['contains', 'equals', 'not_equals', 'exists', 'gt', 'lt', 'in'];

            function addGroup(g) {
                var div = document.createElement('div');
                div.className = 'segment-group';
                div.setAttribute('data-group', g);
                var title = document.createElement('div');
                title.className = 'segment-group-title';
                title.textContent = 'Group ' + (g + 1);
                var addCond = document.createElement('button');
                addCond.type = 'button'; addCond.className = 'btn btn-sm'; addCond.textContent = '+ condition';
                addCond.addEventListener('click', function () { addCondition(g); });
                title.appendChild(addCond);
                div.appendChild(title);
                addCondition(g, div);
                builder.appendChild(div);
            }

            function addCondition(g, container) {
                var groupEl = container || builder.querySelector('.segment-group[data-group="' + g + '"]');
                var row = document.createElement('div');
                row.className = 'segment-condition';

                var fieldSel = document.createElement('select');
                FIELDS.forEach(function (f) {
                    var o = document.createElement('option'); o.value = f[0]; o.textContent = f[1]; fieldSel.appendChild(o);
                });
                var opSel = document.createElement('select');
                OPS.forEach(function (o) { var el = document.createElement('option'); el.value = o; el.textContent = o; opSel.appendChild(el); });
                var valInput = document.createElement('input');
                valInput.placeholder = 'value';

                var del = document.createElement('button');
                del.type = 'button'; del.textContent = '✕'; del.className = 'btn btn-sm';
                del.addEventListener('click', function () { row.remove(); });

                row.appendChild(fieldSel); row.appendChild(opSel); row.appendChild(valInput); row.appendChild(del);
                groupEl.appendChild(row);
            }

            function addGroupBtn() {
                var g = builder.querySelectorAll('.segment-group').length;
                addGroup(g);
            }

            // Initial group
            addGroup(0);

            // Add-group button
            var addGroupLink = document.createElement('button');
            addGroupLink.type = 'button'; addGroupLink.className = 'btn'; addGroupLink.textContent = '+ Add group (OR)';
            addGroupLink.addEventListener('click', addGroupBtn);
            builder.appendChild(addGroupLink);

            function collect() {
                var groups = [];
                builder.querySelectorAll('.segment-group').forEach(function (groupEl, g) {
                    var rows = [];
                    groupEl.querySelectorAll('.segment-condition').forEach(function (row) {
                        var field = row.querySelectorAll('select')[0].value;
                        var op = row.querySelectorAll('select')[1].value;
                        var value = row.querySelector('input').value;
                        rows.push({ group: g, field: field, operator: op, value: value || null });
                    });
                    if (rows.length) groups = groups.concat(rows);
                });
                return groups;
            }

            function refresh() {
                conditionsInput.value = JSON.stringify(collect());
                fetch("{{ route('segments.preview') }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                               'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ conditions: conditionsInput.value })
                }).then(function (r) { return r.json(); }).then(function (d) {
                    countEl.textContent = d.count + ' matching contacts';
                }).catch(function () {});
            }

            previewBtn.addEventListener('click', refresh);
            builder.addEventListener('change', refresh);
            builder.addEventListener('input', refresh);
        })();
    </script>
@endsection
