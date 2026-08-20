<?php

namespace Tests\Unit;

use App\Services\Ai\OpenRouterClient;
use PHPUnit\Framework\TestCase;

class OpenRouterClientTest extends TestCase
{
    public function test_extracts_json_from_plain_content(): void
    {
        $client = new OpenRouterClient('', '');
        $result = $client->extractJson('{"title":"Mobile App"}');
        $this->assertSame(['title' => 'Mobile App'], $result);
    }

    public function test_extracts_json_from_fenced_content(): void
    {
        $client = new OpenRouterClient('', '');
        $result = $client->extractJson("```json\n{\"title\":\"Mobile App\"}\n```");
        $this->assertSame(['title' => 'Mobile App'], $result);
    }

    public function test_returns_null_for_invalid_content(): void
    {
        $client = new OpenRouterClient('', '');
        $this->assertNull($client->extractJson('This is not JSON at all'));
    }

    public function test_continue_with_reasoning_preserves_details(): void
    {
        $client = new OpenRouterClient('', '');
        $messages = $client->continueWithReasoning([['role' => 'user', 'content' => 'hi']], [
            'content' => 'thinking...',
            'reasoning_details' => ['tokens' => 5],
        ]);

        $this->assertCount(2, $messages);
        $this->assertSame('assistant', $messages[1]['role']);
        $this->assertSame('thinking...', $messages[1]['content']);
        $this->assertSame(['tokens' => 5], $messages[1]['reasoning_details']);
    }
}
