@extends('layouts.app')

@section('title', 'Analytics')

@section('content')
    <div class="row">
        <h1>Analytics</h1>
        <select id="period">
            <option value="7">7 days</option>
            <option value="30" selected>30 days</option>
            <option value="90">90 days</option>
        </select>
    </div>
    <div id="analytics" class="cards">
        <p>Loading…</p>
    </div>
@endsection

@push('scripts')
<script>
    const load = async (days) => {
        const res = await fetch(`{{ route('analytics.data') }}?days=${days}`);
        const d = await res.json();
        document.getElementById('analytics').innerHTML = `
            <div class="stat"><span>Messages sent</span><strong>${d.messages.sent}</strong></div>
            <div class="stat"><span>Messages received</span><strong>${d.messages.received}</strong></div>
            <div class="stat"><span>Automation runs</span><strong>${d.automations.runs}</strong></div>
            <div class="stat"><span>Automation conv. rate</span><strong>${d.automations.conversion_rate}%</strong></div>
            <div class="stat"><span>Template sends</span><strong>${d.templates.sends}</strong></div>
            <div class="stat"><span>CTWA clicks</span><strong>${d.ctwa.clicks}</strong></div>
            <div class="stat"><span>CTWA revenue</span><strong>${Number(d.ctwa.revenue).toFixed(2)}</strong></div>
            <div class="stat"><span>CTWA ROAS</span><strong>${d.ctwa.roas ?? '—'}</strong></div>
            <div class="stat"><span>Widget CTR</span><strong>${d.widget.ctr}%</strong></div>
            <div class="stat"><span>AI resolution rate</span><strong>${d.ai.resolution_rate}%</strong></div>
            <div class="stat"><span>Escalations</span><strong>${d.ai.escalations}</strong></div>
            <div class="stat"><span>Attributed revenue</span><strong>${Number(d.revenue.attributed).toFixed(2)}</strong></div>
        `;
    };
    document.getElementById('period').addEventListener('change', (e) => load(e.target.value));
    load(30);
</script>
@endpush
