<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A WhatsApp message template with a multi-level approval lifecycle.
 *
 * lifecycle: draft -> compliance_review -> submitted -> in_review ->
 *            approved | rejected -> (published)
 * status:    draft | submitted | approved | rejected | paused
 */
class Template extends Model
{
    use HasFactory;
    use HasUuids;

    public const CATEGORY_MARKETING = 'MARKETING';
    public const CATEGORY_UTILITY = 'UTILITY';
    public const CATEGORY_AUTHENTICATION = 'AUTHENTICATION';

    protected $fillable = [
        'store_id',
        'name',
        'display_name',
        'category',
        'language',
        'body',            // text with {{1}} placeholders
        'header',          // optional: media header config
        'footer',
        'buttons',
        'variables',       // metadata describing each {{N}} variable
        'status',
        'lifecycle',
        'provider_template_id',
        'approval_level',
        'compliance_issues',
        'is_ab_test',       // is part of an A/B experiment
        'ab_group',
        'metrics',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'header' => 'array',
            'buttons' => 'array',
            'variables' => 'array',
            'compliance_issues' => 'array',
            'is_ab_test' => 'boolean',
            'metrics' => 'array',
            'metadata' => 'array',
        ];
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(TemplateVariant::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(TemplateApproval::class);
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }
}
