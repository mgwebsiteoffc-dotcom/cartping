@extends('layouts.app')

@section('title', 'Products')

@section('content')
    <div class="row">
        <h1>Product catalog</h1>
        <form method="POST" action="{{ route('products.sync') }}">
            @csrf
            <button class="btn primary" type="submit">Sync from Shopify</button>
        </form>
    </div>

    <p class="muted">Products synced from your Shopify store. They power the AI agent's product search and the smart widget's contextual CTAs.</p>

    @if ($products->isEmpty())
        <section class="card">
            <p class="muted">No products synced yet. Click "Sync from Shopify" to pull your catalog (requires your Shopify connection + <code>read_products</code> scope).</p>
        </section>
    @else
        <div class="product-grid">
            @foreach ($products as $p)
                <div class="product-card">
                    @if ($p->featured_image)
                        <img src="{{ $p->featured_image }}" alt="{{ $p->title }}" loading="lazy">
                    @else
                        <div class="product-thumb-placeholder">🛍️</div>
                    @endif
                    <div class="product-info">
                        <strong>{{ $p->title }}</strong>
                        <span>{{ number_format($p->price_min, 2) }} {{ $p->currency }}</span>
                        <span class="badge {{ $p->available ? '' : 'error' }}">{{ $p->available ? 'In stock' : 'Out of stock' }}</span>
                    </div>
                </div>
            @endforeach
        </div>
        {{ $products->links() }}
    @endif
@endsection
