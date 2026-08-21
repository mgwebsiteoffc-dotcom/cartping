<?php

namespace App\Services\Whatsapp\Providers;

use App\Data\WhatsappEvent;
use App\Models\WhatsappConnection;
use App\Services\Whatsapp\Contracts\WhatsappProvider;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Whatify WhatsApp provider (External / Server API).
 *
 * Docs: https://whatify.in/api-docs
 * Base: https://whatify.in/api/v1/external   (API-key based)
 * Auth: X-API-Key: wfy_your_api_key_here
 *
 * Send text   -> POST /send-message   { phone, message, whatsapp_account_id? }
 * Send templ. -> POST /send-template  { phone, template_name, body_params, header_params }
 * Status      -> GET  /messages/{id}
 * Templates   -> GET  /templates
 * Ping        -> GET  /ping
 */
class WhatifyProvider implements WhatsappProvider
{
    protected ?WhatsappConnection $connection = null;

    public function using(WhatsappConnection $connection): static
    {
        $this->connection = $connection;

        return $this;
    }

    public function name(): string
    {
        return 'whatify';
    }

    protected function config(): array
    {
        return $this->connection?->driverConfig()
            ?: config('whatsapp.providers.whatify');
    }

    /**
     * Resolve the Whatify external API base URL.
     *
     * The correct endpoint is https://whatify.in/api/v1/external. Older versions
     * / old stored config may reference the deprecated api.whatify.app host — we
     * normalise those away so the integration always uses the live endpoint.
     */
    protected function baseUrl(): string
    {
        $configured = $this->config()['base_url']
            ?? config('whatsapp.providers.whatify.base_url')
            ?? '';

        $url = strtolower(trim($configured));

        // Ignore the deprecated api.whatify.app host (does not exist anymore).
        if ($url === '' || str_contains($url, 'api.whatify.app')) {
            $url = 'https://whatify.in/api/v1/external';
        }

        return rtrim($url, '/');
    }

    protected function apiKey(): string
    {
        $key = $this->config()['api_key'] ?? '';

        abort_unless($key, 500, 'Whatify API key is not configured.');

        return $key;
    }

    protected function headers(): array
    {
        return [
            'X-API-Key' => $this->apiKey(),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }

    protected function request(string $method, string $path, array $body = []): Response
    {
        return Http::withHeaders($this->headers())
            ->timeout(30)
            ->retry(2, 300)
            ->send($method, $this->baseUrl().'/'.ltrim($path, '/'), [
                'json' => $body,
            ]);
    }

    protected function requireOk(Response $response, string $context): array
    {
        if ($response->failed()) {
            Log::channel('whatsapp')->error("Whatify $context failed", [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new \RuntimeException("Whatify $context failed: ".$response->body());
        }

        return $response->json();
    }

    /* ------------------------------ Webhooks ----------------------------- */

    public function verifyWebhook(array $query): array
    {
        $expected = $this->config()['webhook_verify_token'] ?? null;
        $token = $query['verify_token'] ?? $query['hub_verify_token'] ?? null;
        $challenge = $query['challenge'] ?? $query['hub_challenge'] ?? null;

        if ($token && $expected && hash_equals((string) $expected, (string) $token)) {
            return ['challenge' => $challenge, 'success' => true];
        }

        return ['challenge' => null, 'success' => false];
    }

    public function validSignature(string $signature, string $payload): bool
    {
        $secret = $this->config()['webhook_secret'] ?? null;

        if (! $secret) {
            return true;
        }

        return hash_equals((string) $secret, $signature);
    }

    public function normalizeInbound(array $payload): Collection
    {
        // Accept Whatify's { messages: [...] } or a Meta-shaped envelope.
        $messages = $payload['messages']
            ?? $payload['data']['messages']
            ?? $payload['entry'][0]['changes'][0]['value']['messages']
            ?? [];

        $events = collect();
        foreach ($messages as $msg) {
            $events->push(WhatsappEvent::fromProvider('whatify', 'message', [
                'wa_id' => $msg['from'] ?? $msg['wa_id'] ?? $msg['phone'] ?? null,
                'from' => $msg['from'] ?? $msg['wa_id'] ?? null,
                'profile_name' => $msg['profile']['name'] ?? $msg['name'] ?? null,
                'kind' => $msg['type'] ?? ($msg['message'] ?? $msg['text'] ? 'text' : 'unknown'),
                'body' => is_string($msg['message'] ?? null)
                    ? $msg['message']
                    : ($msg['text']['body'] ?? $msg['body'] ?? null),
                'media_url' => $msg['media']['url'] ?? null,
                'media_mime_type' => $msg['media']['mime_type'] ?? null,
                'message_id' => $msg['id'] ?? $msg['message_id'] ?? null,
                'context_wa_id' => $msg['context']['from'] ?? null,
                'reply_to_message_id' => $msg['context']['id'] ?? null,
                'timestamp' => $msg['timestamp'] ?? $msg['created_at'] ?? null,
                'raw' => $msg,
            ]));
        }

        return $events;
    }

    /* ------------------------------ Sending ------------------------------ */

    public function sendText(string $to, string $body, ?string $replyTo = null, array $opts = []): array
    {
        $response = $this->request('POST', '/send-message', [
            'phone' => $to,
            'message' => $body,
            'whatsapp_account_id' => $this->config()['whatsapp_account_id'] ?? null,
        ]);

        return $this->requireOk($response, 'sendText');
    }

    public function sendTemplate(string $to, string $templateName, string $lang, array $components = [], array $opts = []): array
    {
        // Whatify wants body_params / header_params as plain value arrays.
        $bodyParams = collect($components)->firstWhere('type', 'body')['parameters']
            ?? collect($components)->firstWhere('type', 'body')['body'][0]['parameters']
            ?? [];

        $response = $this->request('POST', '/send-template', [
            'phone' => $to,
            'template_name' => $templateName,
            'body_params' => array_map(fn ($p) => $p['text'] ?? $p, $bodyParams),
        ]);

        return $this->requireOk($response, 'sendTemplate');
    }

    /**
     * The Whatify external API exposes send-message for text. Media is not
     * documented for the external API, so fall back to a text message with the
     * media URL so it still reaches the user.
     */
    public function sendMedia(string $to, string $type, string $mediaUrl, ?string $caption = null, ?string $replyTo = null): array
    {
        $body = $mediaUrl;
        if ($caption) {
            $body = $caption."\n".$mediaUrl;
        }

        return $this->sendText($to, $body, $replyTo);
    }

    public function sendInteractive(string $to, array $interactive, ?string $replyTo = null): array
    {
        $body = $interactive['body']['text'] ?? ($interactive['text'] ?? 'Choose an option:');
        foreach (($interactive['action']['buttons'] ?? []) as $btn) {
            $body .= "\n• ".($btn['title'] ?? $btn['reply']['title'] ?? '');
        }

        return $this->sendText($to, $body, $replyTo);
    }

    /* ------------------------------ Templates ---------------------------- */

    public function createTemplate(array $payload): array
    {
        // The Whatify external API only supports GET /templates — it does NOT
        // expose a create-template endpoint. Templates must be created in the
        // Whatify dashboard. We return a structured "external" marker so the
        // caller can mark the template as dashboard-managed instead of failing.
        throw new \App\Exceptions\TemplateMustBeCreatedInDashboardException(
            'Whatify templates must be created in the Whatify dashboard (the external API only lists templates). Paste the approved template name here after creating it in Whatify.'
        );
    }

    public function listTemplates(): array
    {
        try {
            $response = $this->request('GET', '/templates');
            $json = $this->requireOk($response, 'listTemplates');

            return collect($json['templates'] ?? [])
                ->map(fn ($t) => [
                    'id' => (string) ($t['id'] ?? ''),
                    'name' => $t['name'] ?? '',
                    'status' => strtolower($t['status'] ?? ''),
                    'category' => isset($t['category']) ? strtoupper($t['category']) : null,
                    'language' => $t['language'] ?? null,
                    'raw' => $t,
                ])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            Log::channel('whatsapp')->warning('Whatify listTemplates failed', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public function templateStatus(string $providerTemplateId): array
    {
        $response = $this->request('GET', '/templates/'.$providerTemplateId);

        return $this->requireOk($response, 'templateStatus');
    }

    public function fetchPhoneNumbers(): array
    {
        // Whatify external API doesn't expose phone numbers; return connection info.
        return [
            'whatsapp_account_id' => $this->config()['whatsapp_account_id'] ?? null,
            'display_name' => $this->connection?->display_name,
        ];
    }

    public function ping(): array
    {
        try {
            // Short, non-retrying timeout so the admin iframe never hangs.
            $response = Http::withHeaders([
                'X-API-Key' => $this->apiKey(),
                'Accept' => 'application/json',
            ])->timeout(8)->get($this->baseUrl().'/ping');

            if ($response->failed()) {
                return ['ok' => false, 'error' => 'Whatify returned HTTP '.$response->status().'. Check your API key.'];
            }

            $json = $response->json();

            return ['ok' => ($json['status'] ?? null) === 'ok', 'error' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'Whatify unreachable: '.$e->getMessage()];
        }
    }
}
