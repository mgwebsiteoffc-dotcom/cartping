<?php

namespace App\Services\Shopify;

use App\Models\ShopifyConnection;
use App\Models\Store;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * Shopify Admin REST client. Works with either the OAuth token (2026: expiring
 * offline token + auto-refresh) or a manual Custom App access token.
 *
 * Token lifecycle (Shopify policy, Apr 2026):
 *   - access token  ~60 min, refresh via refresh token
 *   - refresh token 90 days, must be kept warm
 * We refresh a few minutes BEFORE expiry and guard with a per-shop lock so two
 * concurrent requests don't invalidate each other.
 */
class ShopifyClient
{
    protected string $token;
    protected string $shop;
    protected string $apiVersion;

    protected ?Store $store = null;
    protected ?ShopifyConnection $connection = null;
    protected bool $autoRefresh;

    public function __construct(string $token, string $shop, bool $autoRefresh = false)
    {
        $this->token = $token;
        $this->shop = $shop;
        $this->autoRefresh = $autoRefresh;
        $this->apiVersion = config('shopify.api_version', '2026-07');
    }

    public static function for(Store $store): self
    {
        $connection = $store->shopifyConnection;
        $token = $connection?->access_token ?? $store->access_token;

        abort_unless($token, 409, 'No Shopify access token configured for this store.');

        $client = new self($token, $store->myshopify_domain, $connection?->isOauth() ?? false);
        $client->store = $store;
        $client->connection = $connection;

        return $client;
    }

    /**
     * Ensure a valid, non-expiring token before a request. If the stored access
     * token is within the refresh window, rotate it (with a lock) then continue.
     */
    protected function ensureFreshToken(): void
    {
        $connection = $this->connection ?? null;

        if (! $this->autoRefresh || ! $connection || ! $connection->refresh_token) {
            return; // manual mode or no refresh support → use the token as-is
        }

        if (! $connection->needsRefresh()) {
            return; // still valid
        }

        $lock = Cache::lock("shopify_refresh_{$this->shop}", 30);

        try {
            if (! $lock->get()) {
                // Another request is refreshing; wait for it and reload.
                usleep(250_000);
                $this->connection->refresh();
                $this->token = $this->connection->access_token;

                return;
            }

            $oauth = app(ShopifyOAuth::class);
            $payload = $oauth->refreshAccessToken($this->shop, $connection->refresh_token);

            // Persist atomically so we never store a partial pair.
            DB::transaction(function () use ($connection, $payload) {
                $connection->update([
                    'access_token' => $payload['access_token'] ?? null,
                    'refresh_token' => $payload['refresh_token'] ?? $connection->refresh_token,
                    'expires_at' => isset($payload['expires_in'])
                        ? now()->addSeconds((int) $payload['expires_in'])
                        : null,
                    'refresh_token_expires_at' => isset($payload['refresh_token_expires_in'])
                        ? now()->addSeconds((int) $payload['refresh_token_expires_in'])
                        : null,
                ]);
            });

            $this->token = $payload['access_token'] ?? $connection->access_token;
        } finally {
            optional($lock)->release();
        }
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
        $this->ensureFreshToken();

        $response = Http::withHeaders($this->headers())
            ->get($this->url($path), $query);

        if ($response->status() === 401 && $this->autoRefresh) {
            // Force a refresh and retry once (refresh token may have rotated).
            $this->connection?->refresh();
            $this->token = $this->connection?->access_token;
            $this->ensureFreshToken();

            $response = Http::withHeaders($this->headers())
                ->get($this->url($path), $query);
        }

        $response->throw();

        return $response->json();
    }

    public function post(string $path, array $body = []): array
    {
        $this->ensureFreshToken();

        $response = Http::withHeaders($this->headers())
            ->post($this->url($path), $body);

        if ($response->status() === 401 && $this->autoRefresh) {
            $this->connection?->refresh();
            $this->token = $this->connection?->access_token;
            $this->ensureFreshToken();

            $response = Http::withHeaders($this->headers())
                ->post($this->url($path), $body);
        }

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
