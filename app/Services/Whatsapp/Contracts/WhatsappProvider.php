<?php

namespace App\Services\Whatsapp\Contracts;

use App\Models\WhatsappConnection;
use Illuminate\Support\Collection;

/**
 * Provider-agnostic contract implemented by every WhatsApp backend
 * (Meta Cloud API, Whatify, ...). The rest of the app talks only to this
 * interface via the ProviderManager.
 */
interface WhatsappProvider
{
    /**
     * Set the connection instance the provider should operate against.
     */
    public function using(WhatsappConnection $connection): static;

    /**
     * The provider driver key (meta | whatify).
     */
    public function name(): string;

    /**
     * Verify an inbound webhook handshake (GET verify token challenge).
     *
     * @return array{challenge: mixed, success: bool}
     */
    public function verifyWebhook(array $query): array;

    /**
     * Validate the webhook signature of an inbound request.
     */
    public function validSignature(string $signature, string $payload): bool;

    /**
     * Normalize a raw inbound provider payload into a uniform event DTO.
     *
     * @return Collection<int, \App\Data\WhatsappEvent>
     */
    public function normalizeInbound(array $payload): Collection;

    /**
     * Send a text message. $replyTo allows replying within the 24h session.
     */
    public function sendText(string $to, string $body, ?string $replyTo = null, array $opts = []): array;

    /**
     * Send a template message by template name + language + parameters.
     */
    public function sendTemplate(string $to, string $templateName, string $lang, array $components = [], array $opts = []): array;

    /**
     * Send a media message (image/document/audio/video).
     */
    public function sendMedia(string $to, string $type, string $mediaUrl, ?string $caption = null, ?string $replyTo = null): array;

    /**
     * Send an interactive message (reply buttons / list).
     */
    public function sendInteractive(string $to, array $interactive, ?string $replyTo = null): array;

    /**
     * Upload/submit a message template for provider approval.
     */
    public function createTemplate(array $payload): array;

    /**
     * Fetch template approval status from the provider.
     */
    public function templateStatus(string $providerTemplateId): array;

    /**
     * Fetch the merchant's phone number profile / business display info.
     */
    public function fetchPhoneNumbers(): array;

    /**
     * Test connectivity (validate credentials).
     */
    public function ping(): array;
}
