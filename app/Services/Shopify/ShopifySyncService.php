<?php

namespace App\Services\Shopify;

use App\Models\Product;
use App\Models\ShopifyCustomer;
use App\Models\Store;
use Illuminate\Support\Facades\Log;

/**
 * Synchronously syncs Shopify products + customers into local denormalised
 * tables. Used by controllers (immediate, no queue worker required — important
 * on shared hosting) and by the SyncShopifyData job.
 */
class ShopifySyncService
{
    /**
     * @return array{products: int, customers: int, error: ?string}
     */
    public function sync(Store $store): array
    {
        $connection = $store->shopifyConnection;

        if (! $connection?->access_token) {
            return ['products' => 0, 'customers' => 0, 'error' => 'No Shopify connection/token configured.'];
        }

        try {
            $client = ShopifyClient::for($store);

            $products = $this->syncProducts($client, $store);
            $customers = $this->syncCustomers($client, $store);

            return ['products' => $products, 'customers' => $customers, 'error' => null];
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->error('Shopify sync failed', ['store' => $store->id, 'error' => $e->getMessage()]);

            return ['products' => 0, 'customers' => 0, 'error' => $e->getMessage()];
        }
    }

    protected function syncProducts(ShopifyClient $client, Store $store): int
    {
        $sinceId = 0;
        $count = 0;

        do {
            $products = $client->products($sinceId, 250);
            if ($products === []) {
                break;
            }

            foreach ($products as $p) {
                $variants = collect($p['variants'] ?? []);
                $mainImage = $p['image']['src']
                    ?? $p['featured_image']['src']
                    ?? ($p['images'][0]['src'] ?? null);

                Product::updateOrCreate(
                    ['store_id' => $store->id, 'shopify_product_id' => $p['id']],
                    [
                        'title' => $p['title'],
                        'handle' => $p['handle'],
                        'body_html' => $p['body_html'],
                        'product_type' => $p['product_type'],
                        'vendor' => $p['vendor'],
                        'tags' => $p['tags'] ? explode(', ', $p['tags']) : [],
                        'featured_image' => $mainImage,
                        'price_min' => $variants->min('price'),
                        'price_max' => $variants->max('price'),
                        'currency' => $store->currency,
                        'available' => $variants->where('inventory_quantity', '>', 0)->isNotEmpty(),
                        'inventory_total' => $variants->sum('inventory_quantity'),
                        'status' => $p['status'] === 'active' ? 'active' : 'archived',
                    ]
                );
                $count++;
            }

            $sinceId = $p['id'];
        } while (count($products) === 250);

        return $count;
    }

    protected function syncCustomers(ShopifyClient $client, Store $store): int
    {
        $customers = $client->customers();
        $count = 0;

        foreach ($customers as $c) {
            ShopifyCustomer::updateOrCreate(
                ['store_id' => $store->id, 'shopify_id' => $c['id']],
                [
                    'email' => $c['email'],
                    'first_name' => $c['first_name'],
                    'last_name' => $c['last_name'],
                    'phone' => $c['phone'],
                    'accepts_marketing' => $c['accepts_marketing'],
                    'total_orders' => $c['orders_count'] ?? 0,
                    'total_spent' => $c['total_spent'] ?? 0,
                    'lifetime_value' => $c['total_spent'] ?? 0,
                    'last_order_at' => $c['last_order_name'] ?? null,
                    'address' => $c['default_address'] ?? [],
                    'raw' => $c,
                ]
            );
            $count++;
        }

        return $count;
    }
}
