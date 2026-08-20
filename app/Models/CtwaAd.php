<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Click-to-WhatsApp ad. The ad creative deep-links to WhatsApp via our
 * tracking redirect, so we can attribute click -> conversation -> order.
 */
class CtwaAd extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'store_id',
        'name',
        'meta_ad_id',
        'meta_campaign_id',
        'meta_adset_id',
        'destination_wa_number',
        'ctwa_template_id',
        'tracking_url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'is_active',
        'daily_budget',
        'currency',
        'cost',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'daily_budget' => 'float',
            'cost' => 'float',
            'metadata' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function clicks(): HasMany
    {
        return $this->hasMany(ClickEvent::class);
    }
}
