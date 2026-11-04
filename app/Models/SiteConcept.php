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
 * @property int|null $design_template_id
 * @property array<string, mixed> $content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Lead $lead
 * @property-read SiteDesignTemplate|null $designTemplate
 */
#[Fillable(['team_id', 'lead_id', 'batch', 'template', 'design_template_id', 'content'])]
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
     * Get the catalog design (layout variants) this concept was built with.
     *
     * @return BelongsTo<SiteDesignTemplate, $this>
     */
    public function designTemplate(): BelongsTo
    {
        return $this->belongsTo(SiteDesignTemplate::class);
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
