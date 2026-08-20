<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Denormalised snapshot of a Shopify order used by the agent (order tracking)
 * and by notification/attribution logic. Keyed on the Shopify order id.
 */
class Order extends Model
{
    use HasFactory;
    use HasUuids;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REFUNDED = 'refunded';

    protected $fillable = [
        'store_id',
        'shopify_order_id',
        'shopify_customer_id',
        'contact_id',
        'order_number',
        'name',
        'status',
        'financial_status',
        'fulfillment_status',
        'currency',
        'subtotal_price',
        'total_price',
        'total_discounts',
        'total_shipping',
        'total_tax',
        'line_items',
        'shipping_address',
        'billing_address',
        'tracking_number',
        'tracking_company',
        'tracking_url',
        'placed_at',
        'cancelled_at',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'placed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'subtotal_price' => 'float',
            'total_price' => 'float',
            'total_discounts' => 'float',
            'total_shipping' => 'float',
            'total_tax' => 'float',
            'line_items' => 'array',
            'shipping_address' => 'array',
            'billing_address' => 'array',
            'raw' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(ShopifyCustomer::class, 'shopify_customer_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function isShipped(): bool
    {
        return in_array($this->status, [self::STATUS_SHIPPED, self::STATUS_FULFILLED], true);
    }

    public function shortLineSummary(): string
    {
        return collect($this->line_items ?? [])
            ->map(fn ($li) => ($li['quantity'] ?? 1).'x '.($li['title'] ?? 'Item'))
            ->implode(', ');
    }
}
