<?php

namespace App\Console\Commands;

use App\Services\Ai\OpenRouterClient;
use Illuminate\Console\Command;

/**
 * Mirrors the reference OpenRouter Python script: first call creates a task
 * with reasoning enabled, second call (preserving reasoning_details) emits the
 * requested JSON. Run:  php artisan cartping:ai:demo
 */
class AiDemoCommand extends Command
{
    protected $signature = 'cartping:ai:demo {--task=create task to create mobile app, delivery date is 29 aug 2026}';

    protected $description = 'Demo OpenRouter reasoning + reasoning_details continuation (mirrors reference script)';

    public function handle(OpenRouterClient $client): int
    {
        $model = config('ai.reasoning_model');

        $this->info("Model: {$model}");

        // First call with reasoning.
        $result = $client->chat(
            [['role' => 'user', 'content' => $this->option('task')]],
            $model,
            reasoning: true
        );

        $assistant = $result['message'];
        $this->line('--- First response content ---');
        $this->line($assistant['content'] ?? '(empty)');

        // Preserve reasoning_details and continue.
        $messages = [
            ['role' => 'user', 'content' => 'create json with these fields [title,start date,end date,description,client]'],
        ];
        $messages = $client->continueWithReasoning($messages, $assistant);
        $messages[] = ['role' => 'user', 'content' => 'Are you sure? Think carefully.'];

        $result2 = $client->chat($messages, $model, reasoning: true);

        $this->newLine();
        $this->line('--- Second response (JSON) ---');
        $this->line($result2['message']['content'] ?? '(empty)');

        return self::SUCCESS;
    }
}
