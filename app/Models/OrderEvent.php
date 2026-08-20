<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A raw Shopify topic event (orders/create, orders/fulfilled,
 * fulfillment_events/create, orders/cancelled, checkouts/update, customers/*)
 * persisted for idempotent, queued processing.
 */
class OrderEvent extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'store_id',
        'shop',
        'topic',
        'shopify_order_id',
        'api_version',
        'shopify_domain',
        'payload',
        'processed_at',
        'processed_by_job',
        'error',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
