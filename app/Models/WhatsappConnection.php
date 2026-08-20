<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Per-tenant WhatsApp provider credentials and status.
 * provider: meta | whatify  (see config('whatsapp.providers'))
 *
 * Meta uses:   token (access token) + phone_number_id + waba_id
 * Whatify uses: api_key (+ optional api_secret) generated from the Whatify dashboard.
 */
class WhatsappConnection extends Model
{
    use HasFactory;
    use HasUuids;

    public const PROVIDER_META = 'meta';
    public const PROVIDER_WHATIFY = 'whatify';

    protected $fillable = [
        'store_id',
        'provider',
        'display_name',
        'phone_number_id',   // Meta
        'waba_id',           // Meta Business Account id
        'token',             // Meta access token
        'api_key',           // Whatify API key (X-API-Key)
        'api_secret',        // Whatify API secret (if provided)
        'base_url',
        'webhook_verify_token',
        'webhook_secret',
        'is_connected',
        'connected_at',
        'settings',
        'meta',
    ];

    protected $hidden = ['token', 'api_key', 'api_secret', 'webhook_secret'];

    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'api_key' => 'encrypted',
            'api_secret' => 'encrypted',
            'webhook_secret' => 'encrypted',
            'is_connected' => 'boolean',
            'connected_at' => 'datetime',
            'settings' => 'array',
            'meta' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function driverConfig(): array
    {
        $defaults = config("whatsapp.providers.{$this->provider}", []);

        return array_merge($defaults, [
            'token' => $this->token,
            'api_key' => $this->api_key,
            'api_secret' => $this->api_secret,
            'phone_number_id' => $this->phone_number_id,
            'base_url' => $this->base_url ?: ($defaults['base_url'] ?? null),
            'webhook_verify_token' => $this->webhook_verify_token,
            'webhook_secret' => $this->webhook_secret,
        ]);
    }
}
