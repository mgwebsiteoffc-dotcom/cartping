<?php

namespace App\Http\Controllers\Shopify;

use App\Http\Controllers\Controller;
use App\Jobs\SyncShopifyData;
use App\Models\Product;

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
        ]);
    }

    /**
     * Re-sync the product catalog from Shopify on demand.
     */
    public function sync()
    {
        $store = request()->user('store');

        dispatch(new SyncShopifyData($store));

        return back()->with('status', 'Product sync started. Check back shortly.');
    }
}
