<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A browser session captured by the widget. Tracks viewed products (abandoned
 * browse), cart contents, page types and opt-in data — powering abandoned
 * browse recovery and the contextual widget CTAs.
 */
class WidgetSession extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'store_id',
        'session_key',        // fingerprint set in the widget cookie
        'contact_id',
        'fingerprint',        // hashed identifier (for cross-device + CTWA join)
        'page_type',          // product | cart | order | home | collection | blog
        'landed_at',
        'last_active_at',
        'viewed_product_ids', // JSON array
        'cart',               // {currency, item_count, total, line_items: []}
        'cart_token',
        'checkout_url',
        'opted_in_wa',        // captured a WhatsApp number via popup
        'wa_number',
        'source',             // widget | ctwa | organic
        'ctwa_click_id',
        'referrer',
        'bounced',            // exited intent triggered exit popup
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'landed_at' => 'datetime',
            'last_active_at' => 'datetime',
            'viewed_product_ids' => 'array',
            'cart' => 'array',
            'opted_in_wa' => 'boolean',
            'bounced' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function cartTotal(): ?float
    {
        return isset($this->cart['total']) ? (float) $this->cart['total'] : null;
    }
}
