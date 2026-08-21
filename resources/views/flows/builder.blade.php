@extends('layouts.app')

@section('title', 'Flow builder — ' . $flow->name)

@section('content')
    <div class="row">
        <h1>Flow builder — {{ $flow->name }}</h1>
        <div class="row">
            <span id="flow-status" class="badge {{ $flow->is_active ? '' : 'warning' }}">{{ $flow->is_active ? 'Active' : 'Draft' }}</span>
            <button id="btn-save" class="btn primary">Save</button>
            <button id="btn-toggle" class="btn">{{ $flow->is_active ? 'Deactivate' : 'Activate' }}</button>
        </div>
    </div>

    <div class="flow-layout">
        <aside class="flow-palette">
            <h3>Nodes</h3>
            <button class="node-add" data-type="message">💬 Message</button>
            <button class="node-add" data-type="template">📦 Template</button>
            <button class="node-add" data-type="delay">⏱️ Delay</button>
            <button class="node-add" data-type="condition">🔀 Condition</button>
            <button class="node-add" data-type="assign_human">🧑‍💼 Assign human</button>
            <button class="node-add" data-type="end">⏹️ End</button>

            <hr>
            <form method="POST" action="{{ route('flows.test-run', $flow) }}" class="stack">
                @csrf
                <label>Test number<input name="test_number" placeholder="+15551234567"></label>
                <button class="btn" type="submit">Run test</button>
            </form>
            <a class="btn" href="{{ route('flows.runs', $flow) }}">View runs</a>
        </aside>

        <main class="flow-canvas" id="flow-canvas">
            <p class="canvas-hint">Add nodes from the palette. Connect them via the "next" dropdowns inside each node.</p>
        </main>
    </div>

    <script>
        (function () {
            var templates = @json($templates->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]));
            var initial = @json($flow->nodes ?? []);
            var nodes = initial.length ? initial : [];
            var active = {{ $flow->is_active ? 'true' : 'false' }};
            var canvas = document.getElementById('flow-canvas');
            var nextId = 1;

            function nid() {
                return 'n' + (nextId++);
            }

            function render() {
                canvas.innerHTML = '';
                if (!nodes.length) {
                    canvas.innerHTML = '<p class="canvas-hint">No nodes yet. Add a node to begin.</p>';
                    return;
                }
                nodes.forEach(function (node) {
                    canvas.appendChild(nodeCard(node));
                });
            }

            function nodeCard(node) {
                var el = document.createElement('div');
                el.className = 'flow-node type-' + node.type;
                el.setAttribute('data-id', node.id);

                var title = document.createElement('div');
                title.className = 'flow-node-title';
                title.textContent = icon(node.type) + ' ' + label(node.type);
                el.appendChild(title);

                // Type-specific config
                var config = document.createElement('div');
                config.className = 'flow-node-config';
                config.appendChild(field('text', 'Text', node, 'text'));
                config.appendChild(field('template_name', 'Template', node, 'select', templates.map(t => [t.name, t.name])));
                config.appendChild(field('seconds', 'Delay (seconds)', node, 'number'));
                config.appendChild(conditionFields(node));
                config.appendChild(field('reason', 'Reason', node, 'text'));
                el.appendChild(config);

                // Connections
                var conn = document.createElement('div');
                conn.className = 'flow-node-conn';
                if (node.type !== 'end') {
                    conn.appendChild(connectField('next', 'Next →', node));
                }
                if (node.type === 'condition') {
                    conn.appendChild(connectField('true_next', 'If TRUE →', node));
                    conn.appendChild(connectField('false_next', 'If FALSE →', node));
                }
                el.appendChild(conn);

                // Actions
                var actions = document.createElement('div');
                actions.className = 'flow-node-actions';
                var del = document.createElement('button');
                del.textContent = 'Delete';
                del.className = 'btn';
                del.addEventListener('click', function () {
                    nodes = nodes.filter(function (n) { return n.id !== node.id; });
                    // clear pointers to this node
                    nodes.forEach(function (n) {
                        ['next', 'true_next', 'false_next'].forEach(function (k) {
                            if (n[k] === node.id) n[k] = null;
                        });
                    });
                    render();
                });
                actions.appendChild(del);
                el.appendChild(actions);

                return el;
            }

            function field(key, placeholder, node, type, options) {
                var wrap = document.createElement('label');
                wrap.className = 'flow-field';
                var labelEl = document.createElement('span');
                labelEl.textContent = placeholder;
                wrap.appendChild(labelEl);

                var nodeData = node.data = node.data || {};

                if (type === 'select') {
                    var sel = document.createElement('select');
                    options.forEach(function (o) {
                        var opt = document.createElement('option');
                        opt.value = o[1]; opt.textContent = o[0];
                        if (nodeData[key] === o[1]) opt.selected = true;
                        sel.appendChild(opt);
                    });
                    sel.addEventListener('change', function () { nodeData[key] = sel.value; });
                    wrap.appendChild(sel);
                } else {
                    var input = document.createElement('input');
                    input.type = type === 'number' ? 'number' : 'text';
                    input.value = nodeData[key] != null ? nodeData[key] : '';
                    input.addEventListener('input', function () { nodeData[key] = input.value; });
                    wrap.appendChild(input);
                }

                return wrap;
            }

            function conditionFields(node) {
                var wrap = document.createElement('div');
                if (node.type !== 'condition') return wrap;
                wrap.className = 'flow-condition';
                wrap.appendChild(field('field', 'Condition field', node, 'select', [
                    ['contact.opted_in', 'contact.opted_in'],
                    ['contact.name', 'contact.name'],
                    ['message_contains', 'message_contains'],
                ]));
                wrap.appendChild(field('operator', 'Operator', node, 'select', [
                    ['exists', 'exists'],
                    ['equals', 'equals'],
                    ['contains', 'contains'],
                ]));
                wrap.appendChild(field('value', 'Value', node, 'text'));
                return wrap;
            }

            function connectField(key, placeholder, node) {
                var wrap = document.createElement('label');
                wrap.className = 'flow-field';
                var labelEl = document.createElement('span');
                labelEl.textContent = placeholder;
                wrap.appendChild(labelEl);

                var sel = document.createElement('select');
                sel.appendChild(new Option('— end —', ''));
                nodes.forEach(function (target) {
                    if (target.id !== node.id) {
                        var opt = document.createElement('option');
                        opt.value = target.id;
                        opt.textContent = label(target.type);
                        if (node[key] === target.id) opt.selected = true;
                        sel.appendChild(opt);
                    }
                });
                sel.addEventListener('change', function () { node[key] = sel.value || null; });
                wrap.appendChild(sel);
                return wrap;
            }

            function icon(t) { return {start:'▶️',message:'💬',template:'📦',delay:'⏱️',condition:'🔀',assign_human:'🧑',end:'⏹️'}[t] || '•'; }
            function label(t) { return {start:'Start',message:'Message',template:'Template',delay:'Delay',condition:'Condition',assign_human:'Assign human',end:'End'}[t] || t; }

            // Add node buttons
            document.querySelectorAll('.node-add').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var type = btn.getAttribute('data-type');
                    var node = { id: nid(), type: type, label: label(type), data: {}, next: null, true_next: null, false_next: null };
                    nodes.push(node);
                    render();
                });
            });

            // Save
            document.getElementById('btn-save').addEventListener('click', function () {
                fetch("{{ route('flows.save', $flow) }}", {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json',
                               'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
                    body: JSON.stringify({ name: "{{ $flow->name }}", nodes: nodes, is_active: active })
                }).then(function (r) { return r.json(); }).then(function (d) {
                    if (d.ok) alert('Flow saved.');
                });
            });

            // Toggle active
            document.getElementById('btn-toggle').addEventListener('click', function () {
                active = !active;
                var s = document.getElementById('flow-status');
                s.textContent = active ? 'Active' : 'Draft';
                s.className = 'badge ' + (active ? '' : 'warning');
                document.getElementById('btn-save').click();
            });

            render();
        })();
    </script>
@endsection
