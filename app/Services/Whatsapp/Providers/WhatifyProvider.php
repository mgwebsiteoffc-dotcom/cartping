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
 * Whatify WhatsApp provider adapter.
 *
 * Whatify exposes a Meta-compatible-ish REST API. This adapter keeps the app
 * provider-agnostic: swap the base URL / auth header to match Whatify's
 * contract. Where exact endpoints differ, map them here so the rest of the
 * codebase never changes.
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

    protected function baseUrl(): string
    {
        return rtrim($this->config()['base_url'] ?? 'https://api.whatify.app/v1', '/');
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.($this->config()['api_token'] ?? $this->config()['token'] ?? ''),
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

    public function verifyWebhook(array $query): array
    {
        $expected = $this->config()['webhook_verify_token'] ?? null;
        $token = $query['hub_verify_token'] ?? $query['verify_token'] ?? null;
        $challenge = $query['hub_challenge'] ?? $query['challenge'] ?? null;

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
        // Whatify may deliver { messages: [...] } or a Meta-shaped envelope.
        $messages = $payload['messages'] ?? $payload['entry'][0]['changes'][0]['value']['messages'] ?? [];

        $events = collect();
        foreach ($messages as $msg) {
            $events->push(WhatsappEvent::fromProvider('whatify', 'message', [
                'wa_id' => $msg['from'] ?? $msg['wa_id'] ?? null,
                'from' => $msg['from'] ?? null,
                'profile_name' => $msg['profile']['name'] ?? null,
                'kind' => $msg['type'] ?? ($msg['body'] ? 'text' : 'unknown'),
                'body' => is_array($msg['text'] ?? null) ? ($msg['text']['body'] ?? null) : ($msg['body'] ?? null),
                'media_url' => $msg['media']['url'] ?? null,
                'media_mime_type' => $msg['media']['mime_type'] ?? null,
                'message_id' => $msg['id'] ?? null,
                'context_wa_id' => $msg['context']['from'] ?? null,
                'reply_to_message_id' => $msg['context']['id'] ?? null,
                'timestamp' => $msg['timestamp'] ?? null,
                'raw' => $msg,
            ]));
        }

        return $events;
    }

    public function sendText(string $to, string $body, ?string $replyTo = null, array $opts = []): array
    {
        $response = $this->request('POST', '/messages', [
            'to' => $to,
            'type' => 'text',
            'text' => ['body' => $body],
            'context' => $replyTo ? ['message_id' => $replyTo] : null,
        ]);

        return $this->requireOk($response, 'sendText');
    }

    public function sendTemplate(string $to, string $templateName, string $lang, array $components = [], array $opts = []): array
    {
        $response = $this->request('POST', '/messages', [
            'to' => $to,
            'type' => 'template',
            'template' => [
                'name' => $templateName,
                'language' => ['code' => $lang],
                'components' => $components,
            ],
        ]);

        return $this->requireOk($response, 'sendTemplate');
    }

    public function sendMedia(string $to, string $type, string $mediaUrl, ?string $caption = null, ?string $replyTo = null): array
    {
        $response = $this->request('POST', '/messages', [
            'to' => $to,
            'type' => $type,
            $type => ['link' => $mediaUrl] + ($caption ? ['caption' => $caption] : []),
            'context' => $replyTo ? ['message_id' => $replyTo] : null,
        ]);

        return $this->requireOk($response, 'sendMedia');
    }

    public function sendInteractive(string $to, array $interactive, ?string $replyTo = null): array
    {
        $response = $this->request('POST', '/messages', [
            'to' => $to,
            'type' => 'interactive',
            'interactive' => $interactive,
            'context' => $replyTo ? ['message_id' => $replyTo] : null,
        ]);

        return $this->requireOk($response, 'sendInteractive');
    }

    public function createTemplate(array $payload): array
    {
        return $this->requireOk($this->request('POST', '/message_templates', $payload), 'createTemplate');
    }

    public function templateStatus(string $providerTemplateId): array
    {
        return $this->requireOk($this->request('GET', '/message_templates/'.$providerTemplateId), 'templateStatus');
    }

    public function fetchPhoneNumbers(): array
    {
        return $this->requireOk($this->request('GET', '/phone_numbers'), 'fetchPhoneNumbers');
    }

    public function ping(): array
    {
        try {
            $this->fetchPhoneNumbers();
            return ['ok' => true];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
