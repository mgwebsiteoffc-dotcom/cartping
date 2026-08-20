<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Smart contextual WhatsApp widget configuration, built via the visual builder.
 * Contains page targeting + display rules for all four widget types plus the
 * entry/exit popups.
 */
class WidgetConfig extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'store_id',
        'enabled',
        'type',                 // simple_button | tooltip | chat_widget | smart_contextual
        'launcher',             // launcher styling: color, position, icon, label
        'simple_button',        // CTA text / number
        'tooltip',              // tooltip text / delay
        'chat_widget',          // embedded chat panel settings
        'smart_contextual',     // per-page-type appearance + CTA mapping
        'entry_popup',          // {enabled, delay_ms, discount, offer, show_times}
        'exit_popup',           // {enabled, mode: qr|form, intent|inactivity, ...}
        'page_targeting',       // {include: [], exclude: [], page_types: []}
        'display_rules',        // frequency cap, devices, logged-in, currency
        'install_mode',         // script_tag | app_embed | manual
        'script_tag_id',
        'session_ttl_minutes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'launcher' => 'array',
            'simple_button' => 'array',
            'tooltip' => 'array',
            'chat_widget' => 'array',
            'smart_contextual' => 'array',
            'entry_popup' => 'array',
            'exit_popup' => 'array',
            'page_targeting' => 'array',
            'display_rules' => 'array',
            'session_ttl_minutes' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
