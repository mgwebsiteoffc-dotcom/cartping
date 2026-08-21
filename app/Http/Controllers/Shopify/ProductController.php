<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Shopify\ShopifySyncService;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index()
    {
        $store = request()->user('store');

        $products = Product::where('store_id', $store->id)
            ->orderBy('updated_at', 'desc')
            ->paginate(25);

        return view('products.index', [
            'store' => $store,
            'products' => $products,
            'shopifyConnected' => (bool) $store->shopifyConnection?->access_token,
        ]);
    }

    /**
     * Re-sync the product catalog from Shopify on demand — synchronously so it
     * works immediately even without a queue worker (shared hosting).
     */
    public function sync(ShopifySyncService $service)
    {
        $store = request()->user('store');

        $result = $service->sync($store);

        if ($result['error']) {
            return back()->withErrors(['sync' => 'Sync failed: '.$result['error']]);
        }

        return back()->with('status', "Synced {$result['products']} product(s) and {$result['customers']} customer(s).");
    }
}
