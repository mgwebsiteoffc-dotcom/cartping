<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A broadcast / campaign: send a template (or message) to a contact segment,
 * optionally scheduled. Sends are processed through the queue with send limits.
 *
 * status: draft | scheduled | sending | completed | cancelled | failed
 * audience: { type: all|tag|segment|manual, value: string|array }
 */
class Campaign extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'campaigns';

    protected $fillable = [
        'store_id',
        'name',
        'template_id',
        'message_body',        // fallback free-text if no template
        'audience',            // JSON
        'schedule_at',         // nullable = send now
        'status',
        'send_limit_per_hour',
        'total_recipients',
        'sent_count',
        'delivered_count',
        'read_count',
        'failed_count',
        'started_at',
        'finished_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'audience' => 'array',
            'schedule_at' => 'datetime',
            'send_limit_per_hour' => 'integer',
            'total_recipients' => 'integer',
            'sent_count' => 'integer',
            'delivered_count' => 'integer',
            'read_count' => 'integer',
            'failed_count' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(CampaignDelivery::class);
    }
}
