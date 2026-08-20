<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ShopifyCustomer extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'store_id',
        'shopify_id',
        'email',
        'first_name',
        'last_name',
        'phone',
        'accepts_marketing',
        'total_orders',
        'total_spent',
        'lifetime_value',
        'last_order_at',
        'orders_count',
        'address',
        'raw',
    ];

    protected function casts(): array
    {
        return [
            'accepts_marketing' => 'boolean',
            'last_order_at' => 'datetime',
            'total_orders' => 'integer',
            'orders_count' => 'integer',
            'total_spent' => 'float',
            'lifetime_value' => 'float',
            'address' => 'array',
            'raw' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
