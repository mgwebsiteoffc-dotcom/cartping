<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Platform user / staff account. In a multi-tenant SaaS a merchant may have
 * several staff seats; each belongs to a single Store tenant.
 */
class User extends Authenticatable
{
    use HasFactory;
    use HasUuids;
    use Notifiable;

    protected $fillable = [
        'store_id',
        'name',
        'email',
        'password',
        'role', // owner | admin | agent | viewer
        'active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function isOwner(): bool
    {
        return $this->role === 'owner';
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isAgent(): bool
    {
        return in_array($this->role, ['superadmin', 'owner', 'admin', 'agent'], true);
    }
}
