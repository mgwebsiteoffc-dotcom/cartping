<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tracks a single scheduled execution of an automation for a contact/order
 * so time-based recovery is idempotent and auditable.
 * state: scheduled | sent | skipped | failed | cancelled
 */
class AutomationRun extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'store_id',
        'automation_id',
        'contact_id',
        'conversation_id',
        'order_id',
        'trigger_event',
        'due_at',
        'attempted_at',
        'state',
        'provider_message_id',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'trigger_event' => 'array',
            'due_at' => 'datetime',
            'attempted_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function automation(): BelongsTo
    {
        return $this->belongsTo(Automation::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
