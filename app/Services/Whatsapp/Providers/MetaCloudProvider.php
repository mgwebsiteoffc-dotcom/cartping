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
 * Meta (WhatsApp Business Platform) Cloud API provider.
 *
 * Docs: https://developers.facebook.com/docs/whatsapp/cloud-api
 */
class MetaCloudProvider implements WhatsappProvider
{
    protected ?WhatsappConnection $connection = null;
    protected string $graphVersion = 'v22.0';
    protected string $baseUrl = 'https://graph.facebook.com';

    public function using(WhatsappConnection $connection): static
    {
        $this->connection = $connection;

        $config = $connection->driverConfig();
        $this->baseUrl = $config['base_url'] ?? $this->baseUrl;
        $this->graphVersion = config('whatsapp.providers.meta.graph_version', 'v22.0');

        return $this;
    }

    public function name(): string
    {
        return 'meta';
    }

    /* ------------------------------ Credentials -------------------------- */

    protected function token(): string
    {
        $token = $this->connection?->token
            ?: config('whatsapp.providers.meta.token');

        abort_unless($token, 500, 'Meta WhatsApp token is not configured.');

        return $token;
    }

    protected function phoneNumberId(): string
    {
        $id = $this->connection?->phone_number_id
            ?: config('whatsapp.providers.meta.phone_number_id');

        abort_unless($id, 500, 'Meta phone number id is not configured.');

        return $id;
    }

    protected function headers(): array
    {
        return [
            'Authorization' => 'Bearer '.$this->token(),
            'Content-Type' => 'application/json',
        ];
    }

    protected function sendGraph(string $path, array $body): Response
    {
        $url = rtrim($this->baseUrl, '/').'/'.$this->graphVersion.'/'.$path;

        return Http::withHeaders($this->headers())
            ->timeout(30)
            ->retry(2, 300)
            ->post($url, $body);
    }

    protected function requireOk(Response $response, string $context): array
    {
        if ($response->failed()) {
            Log::channel('whatsapp')->error("Meta $context failed", [
                'status' => $response->status(),
                'body' => $response->json(),
            ]);
            throw new \RuntimeException("Meta $context failed: ".$response->body());
        }

        return $response->json();
    }

    /* ------------------------------ Webhooks ----------------------------- */

    public function verifyWebhook(array $query): array
    {
        $mode = $query['hub_mode'] ?? null;
        $token = $query['hub_verify_token'] ?? null;
        $challenge = $query['hub_challenge'] ?? null;

        $expected = $this->connection?->webhook_verify_token
            ?: config('whatsapp.providers.meta.webhook_verify_token');

        if ($mode === 'subscribe' && $token && $expected && hash_equals((string) $expected, (string) $token)) {
            return ['challenge' => $challenge, 'success' => true];
        }

        return ['challenge' => null, 'success' => false];
    }

    public function validSignature(string $signature, string $payload): bool
    {
        $secret = $this->connection?->webhook_secret
            ?: config('whatsapp.providers.meta.webhook_secret');

        if (! $secret) {
            return true; // no secret configured → skip verification (dev)
        }

        $computed = hash_hmac('sha256', $payload, $secret);
        return hash_equals('sha256='.$computed, $signature);
    }

    public function normalizeInbound(array $payload): Collection
    {
        $events = collect();

        foreach ($payload['entry'] ?? [] as $entry) {
            foreach ($entry['changes'] ?? [] as $change) {
                $field = $change['field'] ?? null;
                $value = $change['value'] ?? [];

                if ($field !== 'messages') {
                    continue;
                }

                $metadata = $value['metadata'] ?? [];

                foreach ($value['messages'] ?? [] as $msg) {
                    $events->push($this->messageToEvent($msg, $metadata));
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    $events->push($this->statusToEvent($status));
                }
            }
        }

        return $events;
    }

    protected function messageToEvent(array $msg, array $metadata): WhatsappEvent
    {
        $from = $msg['from'] ?? null;
        $type = $msg['type'] ?? 'text';
        $body = null;
        $mediaUrl = null;
        $mime = null;

        switch ($type) {
            case 'text':
                $body = $msg['text']['body'] ?? null;
                break;
            case 'image':
                $mediaUrl = $msg['image']['url'] ?? null;
                $mime = $msg['image']['mime_type'] ?? null;
                break;
            case 'document':
                $mediaUrl = $msg['document']['url'] ?? null;
                $mime = $msg['document']['mime_type'] ?? null;
                break;
            case 'audio':
                $mediaUrl = $msg['audio']['url'] ?? null;
                $mime = $msg['audio']['mime_type'] ?? null;
                break;
            case 'video':
                $mediaUrl = $msg['video']['url'] ?? null;
                $mime = $msg['video']['mime_type'] ?? null;
                break;
            case 'button':
                $body = $msg['button']['text'] ?? null;
                break;
            case 'interactive':
                $body = $msg['interactive']['button_reply']['title']
                    ?? $msg['interactive']['list_reply']['title'] ?? null;
                break;
            default:
                $body = null;
        }

        return WhatsappEvent::fromProvider('meta', 'message', [
            'wa_id' => $from,
            'from' => $from,
            'profile_name' => $msg['profile']['name'] ?? $metadata['display_phone_number'] ?? null,
            'kind' => $type,
            'body' => $body,
            'media_url' => $mediaUrl,
            'media_mime_type' => $mime,
            'message_id' => $msg['id'] ?? null,
            'context_wa_id' => $msg['context']['from'] ?? null,
            'reply_to_message_id' => $msg['context']['id'] ?? null,
            'timestamp' => $msg['timestamp'] ?? null,
            'raw' => $msg,
        ]);
    }

    protected function statusToEvent(array $status): WhatsappEvent
    {
        return WhatsappEvent::fromProvider('meta', 'message_status', [
            'wa_id' => $status['recipient_id'] ?? null,
            'message_id' => $status['id'] ?? null,
            'direction' => 'outbound',
            'timestamp' => $status['timestamp'] ?? null,
            'raw' => $status,
        ]);
    }

    /* ------------------------------ Sending ------------------------------ */

    public function sendText(string $to, string $body, ?string $replyTo = null, array $opts = []): array
    {
        $response = $this->sendGraph($this->phoneNumberId().'/messages', [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $to,
            'type' => 'text',
            'text' => ['preview_url' => true, 'body' => $body],
            'context' => $replyTo ? ['message_id' => $replyTo] : null,
        ]);

        return $this->requireOk($response, 'sendText');
    }

    public function sendTemplate(string $to, string $templateName, string $lang, array $components = [], array $opts = []): array
    {
        $response = $this->sendGraph($this->phoneNumberId().'/messages', [
            'messaging_product' => 'whatsapp',
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
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => $type,
            $type => ['link' => $mediaUrl] + ($caption ? ['caption' => $caption] : []),
        ];

        if ($replyTo) {
            $payload['context'] = ['message_id' => $replyTo];
        }

        $response = $this->sendGraph($this->phoneNumberId().'/messages', $payload);

        return $this->requireOk($response, 'sendMedia');
    }

    public function sendInteractive(string $to, array $interactive, ?string $replyTo = null): array
    {
        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $to,
            'type' => 'interactive',
            'interactive' => $interactive,
        ];

        if ($replyTo) {
            $payload['context'] = ['message_id' => $replyTo];
        }

        $response = $this->sendGraph($this->phoneNumberId().'/messages', $payload);

        return $this->requireOk($response, 'sendInteractive');
    }

    /* ------------------------------ Templates ---------------------------- */

    public function createTemplate(array $payload): array
    {
        $response = $this->sendGraph($this->phoneNumberId().'/message_templates', $payload);

        return $this->requireOk($response, 'createTemplate');
    }

    public function templateStatus(string $providerTemplateId): array
    {
        $url = rtrim($this->baseUrl, '/').'/'.$this->graphVersion.'/'.$providerTemplateId;

        $response = Http::withHeaders($this->headers())->get($url);

        return $this->requireOk($response, 'templateStatus');
    }

    public function listTemplates(): array
    {
        $url = rtrim($this->baseUrl, '/').'/'.$this->graphVersion.'/'.$this->phoneNumberId().'/message_templates';

        $response = Http::withHeaders($this->headers())
            ->timeout(10)
            ->get($url);

        if ($response->failed()) {
            Log::channel('whatsapp')->error('Meta listTemplates failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return [];
        }

        return collect($response->json('data', []))
            ->map(fn ($t) => [
                'id' => (string) ($t['id'] ?? ''),
                'name' => $t['name'] ?? '',
                'status' => strtolower($t['status'] ?? ''),
                'category' => $t['category'] ?? null,
                'language' => $t['language'] ?? null,
                'raw' => $t,
            ])
            ->values()
            ->all();
    }

    public function fetchPhoneNumbers(): array
    {
        $wabaId = $this->connection?->waba_id;

        if (! $wabaId) {
            // Without a known WABA, derive from phone_number_id messaging profile.
            $url = rtrim($this->baseUrl, '/').'/'.$this->graphVersion.'/'.$this->phoneNumberId();

            $response = Http::withHeaders($this->headers())->get($url);

            return $this->requireOk($response, 'fetchPhoneNumbers');
        }

        $url = rtrim($this->baseUrl, '/').'/'.$this->graphVersion.'/'.$wabaId.'/phone_numbers';
        $response = Http::withHeaders($this->headers())->get($url);

        return $this->requireOk($response, 'fetchPhoneNumbers');
    }

    public function ping(): array
    {
        try {
            // Short, non-retrying timeout so the admin iframe never hangs waiting
            // on a slow provider. Missing phone_number_id produces a clear error.
            if (! $this->connection?->phone_number_id) {
                return ['ok' => false, 'error' => 'Phone number ID is required for Meta.'];
            }

            $url = rtrim($this->baseUrl, '/').'/'.$this->graphVersion.'/'.$this->phoneNumberId();

            $response = Http::withHeaders(['Authorization' => 'Bearer '.$this->token()])
                ->timeout(8)
                ->get($url);

            if ($response->successful()) {
                return ['ok' => true];
            }

            return ['ok' => false, 'error' => 'Meta returned HTTP '.$response->status().'. Check your token & phone number ID.'];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}
