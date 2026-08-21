<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A SaaS subscription plan. Stores are assigned a plan (optionally with an
 * expiry). Limits/features drive what a store can do.
 */
class Plan extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'name',
        'code',
        'price_monthly',
        'price_yearly',
        'limits',
        'features',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'price_monthly' => 'float',
            'price_yearly' => 'float',
            'limits' => 'array',
            'features' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function stores(): HasMany
    {
        return $this->hasMany(Store::class);
    }

    public function hasFeature(string $feature): bool
    {
        return (bool) ($this->features[$feature] ?? false);
    }

    public function limit(string $key, $default = null)
    {
        return $this->limits[$key] ?? $default;
    }
}
