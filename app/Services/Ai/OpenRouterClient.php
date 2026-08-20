<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin OpenRouter client mirroring the reference Python implementation.
 *
 * Supports:
 *  - `reasoning.enabled` for models with reasoning (e.g.
 *    nvidia/nemotron-3.5-lightning:free) — great for structured task parsing.
 *  - preserving `reasoning_details` between turns so a model continues
 *    reasoning where it left off.
 *  - OpenAI function calling for the conversational store agent.
 */
class OpenRouterClient
{
    public function __construct(
        protected string $baseUrl = '',
        protected string $apiKey = '',
        protected string $httpReferer = '',
        protected string $siteUrl = '',
    ) {
        $this->baseUrl = $baseUrl ?: config('ai.base_url');
        $this->apiKey = $apiKey ?: config('ai.api_key');
        $this->httpReferer = $httpReferer ?: config('ai.http_referer');
        $this->siteUrl = $siteUrl ?: config('ai.site_url');
    }

    protected function headers(): array
    {
        $headers = [
            'Authorization' => 'Bearer '.$this->apiKey,
            'Content-Type' => 'application/json',
        ];

        if ($this->httpReferer) {
            $headers['HTTP-Referer'] = $this->httpReferer;
        }
        if ($this->siteUrl) {
            $headers['X-Title'] = $this->siteUrl;
        }

        return $headers;
    }

    /**
     * Chat completion with optional reasoning and optional function tools.
     *
     * @param  array<int, array>  $messages
     * @param  array<int, array>  $tools
     * @return array{message: array, usage: ?array, raw: array}
     */
    public function chat(
        array $messages,
        string $model,
        array $tools = [],
        bool $reasoning = false,
        ?float $temperature = null,
        ?int $maxTokens = null,
        array $extra = [],
    ): array {
        $payload = [
            'model' => $model,
            'messages' => $this->stripNulls($messages),
            'temperature' => $temperature ?? config('ai.temperature', 0.4),
            'max_tokens' => $maxTokens ?? config('ai.max_tokens', 1200),
        ];

        if ($reasoning) {
            $payload['reasoning'] = ['enabled' => true];
        }

        if ($tools !== []) {
            $payload['tools'] = $tools;
            $payload['tool_choice'] = 'auto';
        }

        if ($extra !== []) {
            $payload = array_merge($payload, $extra);
        }

        $response = Http::withHeaders($this->headers())
            ->timeout(config('ai.timeout', 60))
            ->post(rtrim($this->baseUrl, '/').'/chat/completions', $payload);

        if ($response->failed()) {
            Log::channel('ai')->error('OpenRouter request failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            throw new \RuntimeException('OpenRouter request failed: '.$response->body());
        }

        $json = $response->json();
        $message = $json['choices'][0]['message'] ?? [];
        // Normalize to the standard name `content` if the provider uses `tool_calls`.
        $message['content'] = $message['content'] ?? null;

        return [
            'message' => $message,
            'usage' => $json['usage'] ?? null,
            'raw' => $json,
        ];
    }

    /**
     * Build the next messages array preserving reasoning_details, exactly like
     * the reference Python script so a model can continue reasoning.
     */
    public function continueWithReasoning(array $messages, array $assistantMessage): array
    {
        $messages[] = [
            'role' => 'assistant',
            'content' => $assistantMessage['content'] ?? null,
            'reasoning_details' => $assistantMessage['reasoning_details'] ?? null,
        ];

        return $this->stripNulls($messages);
    }

    /**
     * Structured JSON extraction using the reasoning model. Mirrors the
     * reference script: first call asks the model to create a task from a
     * natural-language prompt, second call (continuing reasoning) emits JSON.
     */
    public function structuredJson(
        string $task,
        array $fields,
        string $model = null,
        int $maxTurns = 2,
    ): array {
        $model = $model ?: config('ai.reasoning_model');

        $messages = [
            ['role' => 'user', 'content' => $task],
        ];

        for ($i = 0; $i < $maxTurns; $i++) {
            $result = $this->chat($messages, $model, reasoning: true);
            $assistant = $result['message'];

            $messages = $this->continueWithReasoning($messages, $assistant);

            $content = trim((string) ($assistant['content'] ?? ''));
            $json = $this->extractJson($content);

            if ($json !== null) {
                return $json;
            }

            // Ask it to emit the requested fields specifically.
            $messages[] = [
                'role' => 'user',
                'content' => 'Emit only a valid JSON object with exactly these keys: '
                    .implode(', ', $fields).'. No markdown fences.',
            ];
        }

        throw new \RuntimeException('OpenRouter reasoning model did not return valid JSON.');
    }

    public function extractJson(?string $content): ?array
    {
        if (! $content) {
            return null;
        }

        // Strip ```json ... ``` fences if present.
        if (preg_match('/```(?:json)?\s*(\{.*\})\s*```/s', $content, $m)) {
            $content = $m[1];
        }

        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }

    protected function stripNulls(array $array): array
    {
        return array_map(fn ($m) => array_filter($m, fn ($v) => $v !== null), $array);
    }
}
