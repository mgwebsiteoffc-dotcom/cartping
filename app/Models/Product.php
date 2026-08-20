<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Synced product catalog used by the agent's product_search / inventory tools
 * and by the widget's contextual product card.
 */
class Product extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'products';

    protected $fillable = [
        'store_id',
        'shopify_product_id',
        'title',
        'handle',
        'body_html',
        'product_type',
        'vendor',
        'tags',
        'featured_image',
        'price_min',
        'price_max',
        'currency',
        'available',
        'inventory_total',
        'status',            // active | archived | draft
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'price_min' => 'float',
            'price_max' => 'float',
            'inventory_total' => 'integer',
            'available' => 'boolean',
            'meta' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function url(): string
    {
        return 'https://'.$this->store->myshopify_domain.'/products/'.$this->handle;
    }
}
