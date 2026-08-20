<?php

namespace App\Services\Shopify;

use App\Models\ShopifyConnection;
use App\Models\Store;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Public Shopify App OAuth (mode 1 auth + onboarding).
 * Implements the authorization-code grant with offline access token.
 */
class ShopifyOAuth
{
    protected string $apiKey;
    protected string $apiSecret;

    public function __construct()
    {
        $this->apiKey = config('shopify.api_key');
        $this->apiSecret = config('shopify.api_secret');
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '' && $this->apiSecret !== '';
    }

    /**
     * Build the authorize URL for a given shop domain.
     *
     * The redirect_uri MUST exactly match one of the "Allowed redirection URL(s)"
     * configured in the Shopify app (Settings → App setup). We always derive it
     * from config('app.url') so it never drifts based on which host served the
     * current request (a common cause of "redirect_uri is not whitelisted").
     */
    public function authorizeUrl(string $shop, array $additionalParams = []): string
    {
        $redirectUri = config('shopify.redirect_uri')
            ?: rtrim(config('app.url'), '/').'/auth/shopify/callback';

        $params = array_merge([
            'client_id' => $this->apiKey,
            'scope' => implode(',', config('shopify.scopes')),
            'redirect_uri' => $redirectUri,
            'state' => $this->makeState($shop),
            'grant_options[]' => 'per-user',
        ], $additionalParams);

        return "https://{$shop}/admin/oauth/authorize?".http_build_query($params);
    }

    protected function makeState(string $shop): string
    {
        $state = Str::random(40);
        cache()->put("shopify_oauth_state_{$shop}", $state, now()->addMinutes(10));

        return $state;
    }

    public function verifyState(string $shop, string $state): bool
    {
        return hash_equals((string) cache()->get("shopify_oauth_state_{$shop}", ''), $state);
    }

    /**
     * Exchange the authorization code for an offline access token.
     *
     * As of April 1, 2026 new public apps MUST use expiring offline access
     * tokens. We send `expiring => 1`, and Shopify returns:
     *   { access_token, expires_in, refresh_token, refresh_token_expires_in }
     */
    public function exchangeCode(string $shop, string $code): array
    {
        $response = Http::post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => $this->apiKey,
            'client_secret' => $this->apiSecret,
            'code' => $code,
            'expiring' => 1,
        ]);

        $response->throw();

        return $response->json();
    }

    /**
     * Refresh an expiring offline access token using the refresh token.
     * Returns a fresh { access_token, expires_in, refresh_token, refresh_token_expires_in }.
     */
    public function refreshAccessToken(string $shop, string $refreshToken): array
    {
        $response = Http::post("https://{$shop}/admin/oauth/access_token", [
            'client_id' => $this->apiKey,
            'client_secret' => $this->apiSecret,
            'grant_type' => 'refresh_token',
            'refresh_token' => $refreshToken,
        ]);

        $response->throw();

        return $response->json();
    }

    /**
     * Validate the OAuth callback signature (hmac parameter).
     */
    public function verifyCallback(array $query): bool
    {
        $hmac = $query['hmac'] ?? null;
        if (! $hmac) {
            return false;
        }

        unset($query['hmac'], $query['signature']);
        ksort($query);
        $message = urldecode(http_build_query($query));
        $computed = hash_hmac('sha256', $message, $this->apiSecret);

        return hash_equals($computed, $hmac);
    }

    /**
     * Persist the connected shop as a Store (or update) after successful OAuth.
     */
    public function persistConnection(string $shop, array $tokenPayload, string $scope): Store
    {
        $shop = Str::lower($shop);

        $store = Store::firstOrCreate(
            ['myshopify_domain' => $shop],
            ['name' => $shop, 'currency' => 'USD', 'timezone' => 'UTC']
        );

        $store->update([
            'name' => $store->name,
            'access_token' => $tokenPayload['access_token'] ?? null,
            'shopify_scope' => $scope,
            'onboarding_step' => max($store->onboarding_step, 2),
        ]);

        ShopifyConnection::updateOrCreate(
            ['store_id' => $store->id],
            [
                'mode' => ShopifyConnection::MODE_OAUTH,
                'shop' => $shop,
                'access_token' => $tokenPayload['access_token'] ?? null,
                'refresh_token' => $tokenPayload['refresh_token'] ?? null,
                'scope' => $scope,
                'expires_at' => isset($tokenPayload['expires_in'])
                    ? now()->addSeconds((int) $tokenPayload['expires_in'])
                    : null,
                'refresh_token_expires_at' => isset($tokenPayload['refresh_token_expires_in'])
                    ? now()->addSeconds((int) $tokenPayload['refresh_token_expires_in'])
                    : null,
            ]
        );

        return $store->refresh();
    }
}
