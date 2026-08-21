<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single recipient delivery within a campaign.
 * status: pending | sent | delivered | read | failed | skipped
 */
class CampaignDelivery extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'campaign_deliveries';

    protected $fillable = [
        'store_id',
        'campaign_id',
        'contact_id',
        'status',
        'provider_message_id',
        'sent_at',
        'delivered_at',
        'read_at',
        'error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }
}
