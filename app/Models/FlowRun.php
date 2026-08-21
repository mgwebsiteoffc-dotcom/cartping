<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single execution of a Flow for a contact/conversation.
 * state: running | completed | failed | cancelled
 */
class FlowRun extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'flow_runs';

    protected $fillable = [
        'store_id',
        'flow_id',
        'contact_id',
        'conversation_id',
        'current_node_id',
        'state',
        'steps',
        'started_at',
        'finished_at',
        'error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'steps' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function flow(): BelongsTo
    {
        return $this->belongsTo(Flow::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }
}
