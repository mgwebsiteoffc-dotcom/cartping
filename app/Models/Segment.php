<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A reusable contact segment built from a set of conditions.
 *
 * conditions: [
 *   { group: 0, field: 'tags', operator: 'contains', value: 'vip' },
 *   { group: 1, field: 'consent', operator: 'equals', value: 'OPT_IN' },
 * ]
 * Rows in the same group are ANDed; groups are ORed.
 *
 * field: tags | consent | opted_in | profile_name | email | last_seen_days |
 *        total_orders | lifetime_value | source | wa_id
 * operator: contains | equals | not_equals | exists | gt | lt | in
 */
class Segment extends Model
{
    use HasFactory;
    use HasUuids;

    protected $table = 'segments';

    protected $fillable = [
        'store_id',
        'name',
        'description',
        'conditions',   // JSON
        'is_active',
        'contact_count',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'is_active' => 'boolean',
            'contact_count' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }
}
