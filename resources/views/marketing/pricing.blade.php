@extends('layouts.guest')

@section('title', 'Pricing — CartPing')

@section('content')
    <h1>Simple pricing</h1>
    <p class="lede">Start free, upgrade when you grow. Billing is handled securely through Shopify.</p>

    <div class="pricing-grid">
        <div class="pricing-card">
            <h3>Free</h3>
            <div class="price">₹0<span>/month</span></div>
            <ul>
                <li>✅ 100 WhatsApp messages / month</li>
                <li>✅ AI store agent</li>
                <li>✅ Order &amp; shipping notifications</li>
                <li>✅ Abandoned cart recovery</li>
                <li>✅ Smart WhatsApp widget</li>
                <li>❌ Visual flow builder</li>
            </ul>
            <a class="btn primary" href="{{ route('auth.shopify') }}">Install free</a>
        </div>

        <div class="pricing-card featured">
            <h3>Builder</h3>
            <div class="price">₹999<span>/month</span></div>
            <ul>
                <li>✅ 50,000 WhatsApp messages / month</li>
                <li>✅ Everything in Free</li>
                <li>✅ <strong>Visual flow builder</strong></li>
                <li>✅ Broadcast campaigns</li>
                <li>✅ Advanced segments</li>
                <li>✅ Priority support</li>
            </ul>
            <a class="btn primary" href="{{ route('auth.shopify') }}">Install &amp; upgrade</a>
        </div>
    </div>

    <p class="muted" style="margin-top:1rem">Billing is processed via the Shopify Billing API on your Shopify store. Cancel anytime.</p>
@endsection
