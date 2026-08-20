<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents one of two Shopify integration modes:
 *   oauth   – public Shopify app via OAuth (2026: expiring offline token +
 *             refresh token flow required for new public apps)
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
        'refresh_token',
        'scope',
        'expires_at',                 // access token expiry (60 min, expiring offline tokens)
        'refresh_token_expires_at',   // refresh token expiry (90 days)
        'last_checked_at',
        'meta',
    ];

    protected $hidden = ['access_token', 'refresh_token'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'expires_at' => 'datetime',
            'refresh_token_expires_at' => 'datetime',
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

    /**
     * True when the access token is within the refresh-safety window (or past).
     * We refresh a few minutes BEFORE expiry so live requests always have a
     * valid token, per Shopify's expiring-token best practice.
     */
    public function needsRefresh(int $safetySeconds = 300): bool
    {
        return $this->refresh_token !== null
            && $this->expires_at !== null
            && $this->expires_at->isBefore(now()->addSeconds($safetySeconds));
    }
}
