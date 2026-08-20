<?php

namespace App\Services\Ai\Tools;

use App\Models\KnowledgeBaseChunk;

class FaqSearchTool extends Tool
{
    public function name(): string
    {
        return 'faq_search';
    }

    public function description(): string
    {
        return 'Search the store knowledge base (FAQs, policies, guides) for an answer to a customer question.';
    }

    public function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'question' => ['type' => 'string', 'description' => 'The customer question to find an answer for'],
                'limit' => ['type' => 'integer', 'description' => 'Max chunks to return', 'default' => 3],
            ],
            'required' => ['question'],
        ];
    }

    public function execute(array $arguments, ToolContext $context): ToolResult
    {
        $question = trim($arguments['question'] ?? '');
        $limit = min((int) ($arguments['limit'] ?? 3), 5);

        if (! $question) {
            return ToolResult::fail('No question provided.');
        }

        $terms = preg_split('/\s+/', mb_strtolower($question));
        $chunks = KnowledgeBaseChunk::query()
            ->where('store_id', $context->store->id)
            ->where('is_active', true)
            ->limit(100)
            ->get()
            ->map(function (KnowledgeBaseChunk $chunk) use ($terms) {
                $hay = mb_strtolower($chunk->title.' '.$chunk->content.' '.implode(' ', $chunk->keywords ?? []));
                $score = collect($terms)->filter(fn ($t) => mb_strlen($t) > 2 && str_contains($hay, $t))->count();
                return ['chunk' => $chunk, 'score' => $score];
            })
            ->filter(fn ($r) => $r['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        if ($chunks->isEmpty()) {
            return ToolResult::fail('No matching knowledge base entry was found for that question.');
        }

        return ToolResult::ok($chunks->map(fn ($r) => [
            'title' => $r['chunk']->title,
            'content' => $r['chunk']->content,
            'source' => $r['chunk']->source_type,
        ])->all());
    }
}
