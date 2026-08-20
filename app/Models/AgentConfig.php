<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-store AI store agent configuration: personality, enabled tools,
 * escalation triggers, autonomous mode and RAG sources.
 */
class AgentConfig extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'store_id',
        'name',
        'model',                  // OpenRouter model override
        'temperature',
        'enabled',
        'autonomous',             // resolve fully without human unless escalated
        'persona',                // store context / brand voice
        'greeting',
        'timezone',
        'enabled_tools',          // list of tool slugs
        'escalation_triggers',    // keywords/conditions
        'handoff_message',
        'confidence_threshold',
        'rag_enabled',
        'knowledge_base_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'temperature' => 'float',
            'enabled' => 'boolean',
            'autonomous' => 'boolean',
            'enabled_tools' => 'array',
            'escalation_triggers' => 'array',
            'confidence_threshold' => 'float',
            'rag_enabled' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isToolEnabled(string $slug): bool
    {
        return in_array($slug, $this->enabled_tools ?? [], true);
    }

    public function resolveModel(): string
    {
        return $this->model ?: config('ai.model');
    }
}
