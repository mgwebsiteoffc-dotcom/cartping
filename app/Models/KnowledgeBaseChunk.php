<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * RAG knowledge-base chunk (FAQ, policies, product docs). Embedded or keyword
 * searched at query time and injected into the agent prompt.
 */
class KnowledgeBaseChunk extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'store_id',
        'knowledge_base_id',
        'source_type',     // faq | policy | product | manual | document
        'title',
        'content',
        'keywords',
        'embedding',       // optional vector (JSON) for similarity search
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'keywords' => 'array',
            'embedding' => 'array',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
