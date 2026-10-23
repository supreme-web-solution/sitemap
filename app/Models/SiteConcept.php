<?php

namespace App\Models;

use App\Enums\SiteTemplate;
use Database\Factories\SiteConceptFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One of the alternative website designs offered for a lead before one is chosen.
 *
 * @property int $id
 * @property int $team_id
 * @property int $lead_id
 * @property string $batch
 * @property SiteTemplate $template
 * @property array<string, mixed> $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lead $lead
 */
#[Fillable(['team_id', 'lead_id', 'batch', 'template', 'content'])]
class SiteConcept extends Model
{
    /** @use HasFactory<SiteConceptFactory> */
    use HasFactory;

    /**
     * Get the lead the concept was designed for.
     *
     * @return BelongsTo<Lead, $this>
     */
    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template' => SiteTemplate::class,
            'content' => 'array',
        ];
    }
}
