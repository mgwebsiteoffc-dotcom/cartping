<?php

namespace App\Services\Shopify;

use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Shopify Admin REST client. Works with either the OAuth offline token or a
 * manual Custom App access token stored on the connection.
 */
class ShopifyClient
{
    protected string $token;
    protected string $shop;
    protected string $apiVersion = '2024-10';

    public function __construct(string $token, string $shop)
    {
        $this->token = $token;
        $this->shop = $shop;
        $this->apiVersion = config('shopify.api_version', '2024-10');
    }

    public static function for(Store $store): self
    {
        $connection = $store->shopifyConnection;
        $token = $connection?->access_token ?? $store->access_token;

        abort_unless($token, 409, 'No Shopify access token configured for this store.');

        return new self($token, $store->myshopify_domain);
    }

    protected function headers(): array
    {
        return [
            'X-Shopify-Access-Token' => $this->token,
            'Content-Type' => 'application/json',
        ];
    }

    protected function url(string $path): string
    {
        return "https://{$this->shop}/admin/api/{$this->apiVersion}/".ltrim($path, '/');
    }

    public function get(string $path, array $query = []): array
    {
        $response = Http::withHeaders($this->headers())
            ->get($this->url($path), $query);

        $response->throw();

        return $response->json();
    }

    public function post(string $path, array $body = []): array
    {
        $response = Http::withHeaders($this->headers())
            ->post($this->url($path), $body);

        $response->throw();

        return $response->json();
    }

    /* ------------------------- Convenience endpoints --------------------- */

    public function shopInfo(): array
    {
        return $this->get('shop.json')['shop'] ?? [];
    }

    public function orders(int $sinceId = 0, int $limit = 50): array
    {
        return $this->get('orders.json', [
            'status' => 'any',
            'limit' => $limit,
            'since_id' => $sinceId,
        ])['orders'] ?? [];
    }

    public function customers(string $since = '', int $limit = 50): array
    {
        return $this->get('customers.json', [
            'limit' => $limit,
            'updated_at_min' => $since,
        ])['customers'] ?? [];
    }

    public function products(int $sinceId = 0, int $limit = 250): array
    {
        return $this->get('products.json', [
            'limit' => $limit,
            'since_id' => $sinceId,
        ])['products'] ?? [];
    }

    public function abandonedCheckouts(int $limit = 250): array
    {
        return $this->get('checkouts.json', ['status' => 'open', 'limit' => $limit])['checkouts'] ?? [];
    }

    public function createScriptTag(string $src, string $event = 'onload'): array
    {
        return $this->post('script_tags.json', [
            'script_tag' => [
                'src' => $src,
                'event' => $event,
                'display_scope' => 'online_store',
            ],
        ])['script_tag'] ?? [];
    }

    public function listScriptTags(): array
    {
        return $this->get('script_tags.json')['script_tags'] ?? [];
    }

    public function createWebhook(string $topic, string $address): array
    {
        return $this->post('webhooks.json', [
            'webhook' => [
                'topic' => $topic,
                'address' => $address,
                'format' => 'json',
            ],
        ])['webhook'] ?? [];
    }
}
