<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents one of two Shopify integration modes:
 *   oauth   – public Shopify app via OAuth (we hold a long-lived offline token)
 *   manual  – merchant created a Custom App and pasted their admin API token
 *
 * Tokens are always encrypted at rest.
 */
class ShopifyConnection extends Model
{
    use HasFactory;
    use HasUuids;

    public const MODE_OAUTH = 'oauth';
    public const MODE_MANUAL = 'manual';

    protected $fillable = [
        'store_id',
        'mode',
        'shop',
        'access_token',
        'scope',
        'expires_at',
        'last_checked_at',
        'meta',
    ];

    protected $hidden = ['access_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'expires_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isOauth(): bool
    {
        return $this->mode === self::MODE_OAUTH;
    }

    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }
}
