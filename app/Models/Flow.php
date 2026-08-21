<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A visual chat automation flow (like Aisensy/Wati flow builder).
 *
 * The flow is a directed graph of nodes stored as JSON:
 *   nodes: [{ id, type, label, data, next, true_next, false_next }]
 *   edges: (derivable from node next/true_next/false_next)
 *
 * type: start | message | template | delay | condition | assign_human | end
 */
class Flow extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'flows';

    protected $fillable = [
        'store_id',
        'name',
        'description',
        'trigger',          // welcome | new_message | keyword | order_created | ...
        'trigger_value',    // keyword(s) or event name
        'nodes',            // JSON graph definition
        'is_active',
        'runs_count',
        'completions_count',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'nodes' => 'array',
            'is_active' => 'boolean',
            'runs_count' => 'integer',
            'completions_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function runs(): HasMany
    {
        return $this->hasMany(FlowRun::class);
    }

    public function startNode(): ?array
    {
        foreach ($this->nodes ?? [] as $node) {
            if (($node['type'] ?? '') === 'start') {
                return $node;
            }
        }

        return null;
    }

    public function markRun(): void
    {
        $this->increment('runs_count');
    }

    public function markCompletion(): void
    {
        $this->increment('completions_count');
    }
}
