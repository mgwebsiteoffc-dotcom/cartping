<?php

namespace App\Http\Middleware;

use App\Models\Store;
use App\Models\WhatsappConnection;
use App\Services\Whatsapp\WhatsappManager;
use Closure;
use Illuminate\Http\Request;

/**
 * For inbound WhatsApp webhooks, resolves the store + connection for a given
 * provider and verifies the request before handing off to the controller.
 */
class VerifyWhatsappWebhook
{
    public function __construct(protected WhatsappManager $whatsapp)
    {
    }

    public function handle(Request $request, Closure $next, ?string $provider = null)
    {
        // The provider comes from the {provider} route segment when the
        // middleware alias doesn't inject it explicitly.
        $provider = $provider ?: (string) $request->route('provider');

        // Resolve the store owning this provider + phone number.
        $store = $this->resolveStore($request, $provider);

        if (! $store) {
            return response()->json(['error' => 'Unknown store'], 404);
        }

        $connection = $store->whatsappConnection;

        if (! $connection || $connection->provider !== $provider) {
            return response()->json(['error' => 'Provider mismatch'], 404);
        }

        $driver = $this->whatsapp->forConnection($connection);

        // GET = verification handshake.
        if ($request->isMethod('get')) {
            $result = $driver->verifyWebhook($request->query());
            if (! $result['success']) {
                return response()->json(['error' => 'Verification failed'], 403);
            }
            return response($result['challenge'], 200);
        }

        // POST = inbound event with optional signature check.
        $signature = $request->header('X-Hub-Signature-256')
            ?? $request->header('X-Whatify-Signature')
            ?? $request->header('Authorization');

        if (! $driver->validSignature($signature ?? '', $request->getContent())) {
            return response()->json(['error' => 'Invalid signature'], 401);
        }

        $request->attributes->set('whatsapp_store', $store);
        $request->attributes->set('whatsapp_connection', $connection);

        return $next($request);
    }

    protected function resolveStore(Request $request, string $provider): ?Store
    {
        // Best-effort: phone_number_id present in payload, else by provider.
        $phoneNumberId = $request->input('entry.0.changes.0.value.metadata.phone_number_id')
            ?? $request->input('phone_number_id');

        if ($phoneNumberId) {
            $connection = WhatsappConnection::where('phone_number_id', $phoneNumberId)
                ->where('provider', $provider)
                ->first();

            if ($connection) {
                return $connection->store;
            }
        }

        // Fallback: only one store on this provider.
        $connection = WhatsappConnection::where('provider', $provider)
            ->where('is_connected', true)
            ->first();

        return $connection?->store;
    }
}
