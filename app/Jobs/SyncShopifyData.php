<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\ShopifyCustomer;
use App\Models\Store;
use App\Services\Shopify\ShopifyClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Syncs Shopify products + customers into local denormalised tables so the
 * agent's search/inventory/LTV tools run fast and stay within API rate limits.
 */
class SyncShopifyData implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public function __construct(public Store $store)
    {
        $this->onQueue('low');
    }

    public function handle(): void
    {
        $connection = $this->store->shopifyConnection;

        if (! $connection?->access_token) {
            return;
        }

        $client = ShopifyClient::for($this->store);

        $this->syncProducts($client);
        $this->syncCustomers($client);
    }

    protected function syncProducts(ShopifyClient $client): void
    {
        $sinceId = 0;

        do {
            $products = $client->products($sinceId, 250);
            if ($products === []) {
                break;
            }

            foreach ($products as $p) {
                $variants = collect($p['variants'] ?? []);
                Product::updateOrCreate(
                    ['store_id' => $this->store->id, 'shopify_product_id' => $p['id']],
                    [
                        'title' => $p['title'],
                        'handle' => $p['handle'],
                        'body_html' => $p['body_html'],
                        'product_type' => $p['product_type'],
                        'vendor' => $p['vendor'],
                        'tags' => $p['tags'] ? explode(', ', $p['tags']) : [],
                        'featured_image' => $p['image']['src'] ?? null,
                        'price_min' => $variants->min('price'),
                        'price_max' => $variants->max('price'),
                        'currency' => $this->store->currency,
                        'available' => $variants->where('inventory_quantity', '>', 0)->isNotEmpty(),
                        'inventory_total' => $variants->sum('inventory_quantity'),
                        'status' => $p['status'] === 'active' ? 'active' : 'archived',
                    ]
                );
            }

            $sinceId = $p['id'];
        } while (count($products) === 250);
    }

    protected function syncCustomers(ShopifyClient $client): void
    {
        $customers = $client->customers();

        foreach ($customers as $c) {
            ShopifyCustomer::updateOrCreate(
                ['store_id' => $this->store->id, 'shopify_id' => $c['id']],
                [
                    'email' => $c['email'],
                    'first_name' => $c['first_name'],
                    'last_name' => $c['last_name'],
                    'phone' => $c['phone'],
                    'accepts_marketing' => $c['accepts_marketing'],
                    'total_orders' => $c['orders_count'],
                    'total_spent' => $c['total_spent'],
                    'lifetime_value' => $c['total_spent'],
                    'last_order_at' => $c['last_order_name'] ?? null,
                    'address' => $c['default_address'] ?? [],
                    'raw' => $c,
                ]
            );
        }
    }
}
