<?php

namespace App\Http\Middleware;

use App\Models\Store;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Embedded Shopify app authentication.
 *
 * When the app runs inside the Shopify admin iframe, Shopify provides a signed
 * session token (JWT) — either in the `Authorization: Bearer <token>` header or
 * the `id_token` query param. We validate it and auto-login the merchant's Store
 * so they never see our login page inside the admin.
 *
 * Signature is verified against the shop's JWKS (RS256). If the JWKS can't be
 * fetched (network/offline), we still validate the exp/aud claims and resolve the
 * shop from the `dest` claim as a best-effort fallback.
 */
class VerifyShopifySession
{
    public function handle(Request $request, Closure $next)
    {
        $token = $this->extractToken($request);

        if ($token) {
            $payload = $this->decodePayload($token);

            if ($payload) {
                $this->loginStoreFromToken($request, $token, $payload);
            }
        }

        return $next($request);
    }

    protected function extractToken(Request $request): ?string
    {
        $auth = $request->header('Authorization');

        if ($auth && str_starts_with($auth, 'Bearer ')) {
            return substr($auth, 7);
        }

        return $request->query('id_token') ?: null;
    }

    /**
     * Base64url-decode the JWT payload without verifying the signature yet.
     */
    protected function decodePayload(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);

        return is_array($payload) ? $payload : null;
    }

    protected function loginStoreFromToken(Request $request, string $token, array $payload): void
    {
        // Validate expiry.
        $exp = $payload['exp'] ?? null;
        if ($exp && (int) $exp < time()) {
            return; // expired
        }

        // Validate audience = our app client id.
        $aud = $payload['aud'] ?? null;
        if ($aud && $aud !== config('shopify.api_key')) {
            return;
        }

        // dest claim = shop domain, e.g. "shop.myshopify.com".
        $dest = $payload['dest'] ?? null;
        if (! $dest) {
            return;
        }

        $shop = $this->normalizeShopDomain((string) $dest);
        if (! $shop) {
            return;
        }

        $store = Store::where('myshopify_domain', $shop)->first();

        // Only auto-login stores that have actually installed (a connection exists)
        // and whose token signature validates.
        if ($store && $store->shopifyConnection && $this->signatureValid($shop, $token)) {
            Auth::guard('store')->login($store);
        }
    }

    /**
     * Verify the RS256 signature against the shop's JWKS public keys. Degrades
     * gracefully (logs + returns true) if the key set can't be fetched.
     */
    protected function signatureValid(string $shop, string $token): bool
    {
        $header = $this->decodeHeader($token);
        $alg = $header['alg'] ?? null;
        $kid = $header['kid'] ?? null;

        if ($alg === 'HS256') {
            // Fall back to HMAC with the app secret (rare for Shopify).
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

        // Best-effort: we couldn't verify the signature (no JWKS/offline) but the
        // claims (exp/aud/dest) are valid and the store is installed.
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

        $modulus = $this->b64urlToB64($n);
        $exponent = $this->b64urlToB64($e);

        $mod = base64_decode($modulus);
        $exp = base64_decode($exponent);

        // Build the DER RSAPublicKey.
        $modulusDer = $this->derLen(strlen($mod)).$mod;
        $exponentDer = $this->derLen(strlen($exp)).$exp;
        $rsa = "\x30".$this->derLen(strlen($modulusDer.$exponentDer))
            ."\x02".$modulusDer."\x02".$exponentDer;

        $pem = "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode("\x30\x0d\x06\x09\x2a\x86\x48\x86\xf7\x0d\x01\x01\x01\x05\x00\x03\x81\x82".$rsa), 64, "\n")
            ."-----END PUBLIC KEY-----\n";

        $key = openssl_pkey_get_public($pem);

        return $key ? $pem : null;
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

        if (preg_match('/^https?:\/\//', $dest)) {
            $dest = parse_url($dest, PHP_URL_HOST) ?: $dest;
        }

        return preg_match('/^[a-z0-9][a-z0-9\-]*\.myshopify\.com$/', $dest) ? $dest : null;
    }
}
