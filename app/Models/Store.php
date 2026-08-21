<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A merchant tenant. Serves as the authenticated "store guard" principal for
 * the merchant-facing dashboard and acts as the tenant boundary for every
 * domain model.
 *
 * @property string $id
 * @property string $name
 * @property string $myshopify_domain
 * @property string|null $contact_email
 * @property string $currency
 * @property string $timezone
 * @property int $onboarding_step
 * @property bool $onboarding_complete
 * @property array $features
 * @property string|null $shopify_scope
 * @property string|null $access_token (encrypted, manual-custom-app mode)
 * @property array $settings
 */
class Store extends Authenticatable
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'name',
        'myshopify_domain',
        'contact_email',
        'password',
        'currency',
        'timezone',
        'onboarding_step',
        'onboarding_complete',
        'features',
        'shopify_scope',
        'access_token',
        'settings',
        'plan_id',
        'plan_expires_at',
        'disabled_at',
    ];

    protected $hidden = ['access_token', 'password'];

    protected function casts(): array
    {
        return [
            'onboarding_complete' => 'boolean',
            'features' => 'array',
            'settings' => 'array',
            'access_token' => 'encrypted',
            'password' => 'hashed',
            'plan_expires_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    /* ------------------------------ Relations ----------------------------- */

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function shopifyConnection(): HasOne
    {
        return $this->hasOne(ShopifyConnection::class);
    }

    public function whatsappConnection(): HasOne
    {
        return $this->hasOne(WhatsappConnection::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function templates(): HasMany
    {
        return $this->hasMany(Template::class);
    }

    public function widgetConfig(): HasOne
    {
        return $this->hasOne(WidgetConfig::class);
    }

    public function agentConfig(): HasOne
    {
        return $this->hasOne(AgentConfig::class);
    }

    public function automations(): HasMany
    {
        return $this->hasMany(Automation::class);
    }

    public function analyticsEvents(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class);
    }

    /* ------------------------------ Helpers ------------------------------- */

    public function hasCompleted(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }

    public function grantFeature(string $feature): void
    {
        $features = array_unique(array_merge($this->features ?? [], [$feature]));
        $this->update(['features' => $features]);
    }

    public function advanceOnboardingTo(int $step): void
    {
        if ($step >= 6) {
            $this->update(['onboarding_step' => 6, 'onboarding_complete' => true]);
            return;
        }
        $this->update(['onboarding_step' => max($this->onboarding_step, $step)]);
    }

    public function isProvisioned(): bool
    {
        return $this->whatsappConnection()->exists() && $this->agentConfig()->exists();
    }

    public function hasActivePlan(): bool
    {
        if ($this->plan_expires_at && $this->plan_expires_at->isPast()) {
            return false;
        }

        return $this->plan !== null;
    }

    public function isDisabled(): bool
    {
        return $this->disabled_at !== null;
    }

    public function activePlanFeature(string $feature): bool
    {
        if (! $this->hasActivePlan()) {
            return false;
        }

        return $this->plan->hasFeature($feature);
    }
}
