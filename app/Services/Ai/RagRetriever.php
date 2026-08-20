<?php

namespace App\Services\Ai;

use App\Models\KnowledgeBaseChunk;
use App\Models\Store;
use Illuminate\Support\Collection;

/**
 * Retrieves the most relevant knowledge-base chunks for a query.
 * Uses simple keyword scoring (extensible to embeddings when available).
 */
class RagRetriever
{
    public function retrieve(Store $store, string $query, int $limit = null): Collection
    {
        $limit = $limit ?? config('ai.max_rag_chunks', 6);
        $terms = array_values(array_filter(preg_split('/\s+/', mb_strtolower($query))));

        if ($terms === []) {
            return collect();
        }

        $candidates = KnowledgeBaseChunk::query()
            ->where('store_id', $store->id)
            ->where('is_active', true)
            ->limit(200)
            ->get();

        return $candidates
            ->map(function (KnowledgeBaseChunk $chunk) use ($terms) {
                $hay = mb_strtolower(
                    $chunk->title.' '.$chunk->content.' '.implode(' ', $chunk->keywords ?? [])
                );
                $score = collect($terms)
                    ->filter(fn ($t) => mb_strlen($t) > 2 && str_contains($hay, $t))
                    ->count();

                return ['chunk' => $chunk, 'score' => $score];
            })
            ->filter(fn ($r) => $r['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->map(fn ($r) => $r['chunk'])
            ->values();
    }
}
