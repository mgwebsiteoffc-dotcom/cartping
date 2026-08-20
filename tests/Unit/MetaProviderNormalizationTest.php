<?php

namespace Tests\Unit;

use App\Models\WhatsappConnection;
use App\Services\Whatsapp\Providers\MetaCloudProvider;
use PHPUnit\Framework\TestCase;

class MetaProviderNormalizationTest extends TestCase
{
    public function test_normalizes_meta_text_message_payload(): void
    {
        $provider = new MetaCloudProvider();

        $payload = [
            'entry' => [[
                'changes' => [[
                    'field' => 'messages',
                    'value' => [
                        'metadata' => ['display_phone_number' => '15551234567'],
                        'messages' => [[
                            'from' => '15559998888',
                            'id' => 'wamid.ABC123',
                            'timestamp' => '1700000000',
                            'type' => 'text',
                            'text' => ['body' => 'Hello, is this in stock?'],
                        ]],
                    ],
                ]],
            ]],
        ];

        $events = $provider->normalizeInbound($payload);

        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertSame('meta', $event->provider);
        $this->assertSame('message', $event->type);
        $this->assertSame('15559998888', $event->waId);
        $this->assertSame('text', $event->kind);
        $this->assertSame('Hello, is this in stock?', $event->body);
    }

    public function test_verify_webhook_matches_token(): void
    {
        $connection = new WhatsappConnection(['webhook_verify_token' => 'secret']);
        $provider = (new MetaCloudProvider())->using($connection);

        $result = $provider->verifyWebhook([
            'hub_mode' => 'subscribe',
            'hub_verify_token' => 'secret',
            'hub_challenge' => '12345',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('12345', $result['challenge']);
    }
}
