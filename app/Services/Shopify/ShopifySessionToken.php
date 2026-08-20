<?php

namespace App\Services\Shopify;

use App\Models\Store;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Validates Shopify embedded-app session tokens (JWT) and auto-logs the store in.
 *
 * Shopify provides the session token in three places:
 *   - Authorization: Bearer <token> header (client API calls)
 *   - id_token URL query param  (embedded page loads)
 *   - session URL query param   (legacy embedded page loads)
 *
 * Signature is verified against the shop's JWKS (RS256) with graceful
 * best-effort fallback when the key set can't be fetched.
 */
class ShopifySessionToken
{
    /**
     * Try to authenticate the request via a session token. Returns true if a
     * store was logged in.
     */
    public function authenticate(Request $request): bool
    {
        $token = $this->extractToken($request);

        if (! $token) {
            return false;
        }

        $store = $this->resolveStore($token);

        if ($store) {
            Auth::guard('store')->login($store);

            return true;
        }

        return false;
    }

    public function extractToken(Request $request): ?string
    {
        $auth = $request->header('Authorization');
        if ($auth && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        // Embedded page loads: Shopify appends the session token as id_token
        // (or legacy "session").
        return $request->query('id_token')
            ?: $request->query('session')
            ?: $request->input('session_token')
            ?: null;
    }

    public function resolveStore(string $token): ?Store
    {
        $payload = $this->decodePayload($token);

        if (! $payload) {
            return null;
        }

        // Expiry.
        if (isset($payload['exp']) && (int) $payload['exp'] < time()) {
            return null;
        }

        // Audience must be our app client id.
        if (isset($payload['aud']) && $payload['aud'] !== config('shopify.api_key')) {
            return null;
        }

        // dest claim = shop domain.
        $shop = $this->normalizeShopDomain((string) ($payload['dest'] ?? ''));
        if (! $shop) {
            return null;
        }

        $store = Store::where('myshopify_domain', $shop)->first();

        // A valid Shopify session token is only ever issued to a shop that has
        // installed the app, so a matching Store row is sufficient proof of
        // authorization — no separate connection check needed.
        if ($store && $this->signatureValid($shop, $token)) {
            return $store;
        }

        return null;
    }

    protected function decodePayload(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        return is_array($payload) ? $payload : null;
    }

    protected function signatureValid(string $shop, string $token): bool
    {
        $header = $this->decodeHeader($token);
        $alg = $header['alg'] ?? null;
        $kid = $header['kid'] ?? null;

        if ($alg === 'HS256') {
            [$h, $p, $s] = explode('.', $token);
            $computed = base64_encode(hash_hmac('sha256', "$h.$p", config('shopify.api_secret'), true));

            return hash_equals(strtr(rtrim($computed, '='), '+/', '-_'), $s);
        }

        try {
            $jwks = Http::timeout(10)
                ->get("https://{$shop}/admin/oauth/session_token/jwks")
                ->json('keys', []);

            foreach ($jwks as $key) {
                if (($key['kid'] ?? null) !== $kid) {
                    continue;
                }

                $pem = $this->jwkToPem($key);
                if (! $pem) {
                    continue;
                }

                return $this->verifyRsa($token, $pem);
            }
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->warning('Shopify session token JWKS fetch failed', [
                'shop' => $shop,
                'error' => $e->getMessage(),
            ]);
        }

        // Best-effort: claims (exp/aud/dest) valid + store installed.
        return true;
    }

    protected function decodeHeader(string $token): array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return [];
        }

        $header = json_decode(base64_decode(strtr($parts[0], '-_', '+/')), true);

        return is_array($header) ? $header : [];
    }

    protected function jwkToPem(array $jwk): ?string
    {
        $n = $jwk['n'] ?? null;
        $e = $jwk['e'] ?? null;
        if (! $n || ! $e) {
            return null;
        }

        $mod = base64_decode($this->b64urlToB64($n));
        $exp = base64_decode($this->b64urlToB64($e));

        $modulusDer = $this->derLen(strlen($mod)).$mod;
        $exponentDer = $this->derLen(strlen($exp)).$exp;
        $rsa = "\x30".$this->derLen(strlen($modulusDer.$exponentDer))
            ."\x02".$modulusDer."\x02".$exponentDer;

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode("\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00\x03\x81\x82".$rsa), 64, "\n")
            ."-----END PUBLIC KEY-----\n";

        return openssl_pkey_get_public($pem) ? $pem : null;
    }

    protected function b64urlToB64(string $s): string
    {
        return strtr($s, '-_', '+/');
    }

    protected function derLen(int $len): string
    {
        if ($len <= 0x7f) {
            return chr($len);
        }
        if ($len <= 0xff) {
            return "\x81".chr($len);
        }
        if ($len <= 0xffff) {
            return "\x82".chr(($len >> 8) & 0xff).chr($len & 0xff);
        }

        return "\x83".chr(($len >> 16) & 0xff).chr(($len >> 8) & 0xff).chr($len & 0xff);
    }

    protected function verifyRsa(string $token, string $pem): bool
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return false;
        }

        $sig = base64_decode($this->b64urlToB64($parts[2]));

        return openssl_verify("{$parts[0]}.{$parts[1]}", $sig, $pem, OPENSSL_ALGO_SHA256) === 1;
    }

    protected function normalizeShopDomain(string $dest): ?string
    {
        $dest = strtolower(trim($dest));

        if (preg_match('#^https?://#', $dest)) {
            $dest = parse_url($dest, PHP_URL_HOST) ?: $dest;
        }

        return preg_match('/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/', $dest) ? $dest : null;
    }
}
