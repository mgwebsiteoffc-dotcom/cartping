<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A WhatsApp end-user associated with a store. Enriched from Shopify customer
 * data (LTV, order history) and web-session signals (abandoned browse).
 */
class Contact extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'store_id',
        'wa_id',            // WhatsApp number (E.164)
        'profile_name',
        'shopify_customer_id',
        'first_name',
        'last_name',
        'email',
        'phone',
        'opt_in_at',        // GDPR opt-in timestamp
        'opt_in_source',    // widget | ctwa | broadcast | checkout | imported
        'consent_state',    // NOT_REQUIRED | OPT_IN | OPT_OUT | NA
        'last_seen_at',
        'first_seen_at',
        'tags',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'opt_in_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'first_seen_at' => 'datetime',
            'tags' => 'array',
            'metadata' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function latestConversation(): HasOne
    {
        return $this->hasOne(Conversation::class)->latestOfMany();
    }

    public function shopifyCustomer(): BelongsTo
    {
        return $this->belongsTo(ShopifyCustomer::class, 'shopify_customer_id');
    }

    public function hasOptedIn(): bool
    {
        return $this->consent_state === 'OPT_IN' && $this->opt_in_at !== null;
    }

    public function grantOptIn(string $source): void
    {
        $this->update([
            'opt_in_at' => now(),
            'opt_in_source' => $source,
            'consent_state' => 'OPT_IN',
        ]);
    }
}
