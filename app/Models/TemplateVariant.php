<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An A/B variant belonging to a parent template. Each variant goes through the
 * same provider approval pipeline independently.
 */
class TemplateVariant extends Model
{
    use HasFactory;
    use HasUuids;

    protected $fillable = [
        'store_id',
        'template_id',
        'label',               // A | B
        'body',
        'header',
        'buttons',
        'status',
        'lifecycle',
        'provider_template_id',
        'compliance_issues',
        'is_control',
        'metrics',
    ];

    protected function casts(): array
    {
        return [
            'header' => 'array',
            'buttons' => 'array',
            'compliance_issues' => 'array',
            'is_control' => 'boolean',
            'metrics' => 'array',
        ];
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(Template::class);
    }
}
