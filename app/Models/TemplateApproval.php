<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Audit trail / workflow step for a template approval. Captures both internal
 * multi-level review (owner -> compliance officer) and the provider decision.
 */
class TemplateApproval extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'store_id',
        'template_id',
        'variant_id',
        'level',               // internal | compliance | provider
        'status',              // pending | approved | rejected
        'reviewer_id',
        'reviewer_type',
        'comment',
        'decided_at',
        'provider_status',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'decided_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
