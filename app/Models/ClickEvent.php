<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A recorded CTWA ad click. The ad links to our /c/wa/{ad} redirect which sets
 * a browser fingerprint + session cookie, then 302s to wa.me. That fingerprint
 * is later joined to a Shopify order via the conversion API.
 */
class ClickEvent extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'store_id',
        'ctwa_ad_id',
        'click_id',          // generated correlation id
        'fingerprint',       // hashed client id from Shopify/Meta pixel
        'ip',
        'user_agent',
        'referrer',
        'clicked_at',
        'converted',          // whether a matching order was found
        'order_id',
        'order_total',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
            'converted' => 'boolean',
            'order_total' => 'float',
            'metadata' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function ad(): BelongsTo
    {
        return $this->belongsTo(CtwaAd::class, 'ctwa_ad_id');
    }
}
