<?php

namespace App\Http\Resources;

use App\Models\Lead;
use App\Services\Prospecting\OpportunityScorer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lead
 */
class LeadResource extends JsonResource
{
    /**
     * Disable wrapping so the resource can be resolved straight into Inertia props.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category,
            'phone' => $this->phone,
            'email' => $this->email,
            'website' => $this->website,
            'address' => $this->address,
            'city' => $this->city,
            'rating' => $this->rating,
            'reviewsCount' => $this->reviews_count,
            'mapsUrl' => $this->maps_url,
            'status' => $this->status->value,
            'assignedUserId' => $this->assigned_user_id,
            'dealValue' => $this->deal_value,
            'opportunityScore' => $this->opportunity_score,
            'opportunityTier' => OpportunityScorer::tier($this->opportunity_score),
            'websiteAuditScore' => $this->website_audit && empty($this->website_audit['error'])
                ? $this->website_audit['score']
                : null,
            'nextFollowUpAt' => $this->next_follow_up_at?->toDateString(),
            'lastContactedAt' => $this->last_contacted_at?->toISOString(),
            'detailsConfirmed' => $this->details_confirmed_at !== null,
            'siteGenerationStatus' => $this->site_generation_status->value,
            'siteGenerationError' => $this->site_generation_error,
            'site' => $this->whenLoaded('site', fn () => $this->site ? [
                'id' => $this->site->id,
                'previewUrl' => route('sites.preview', $this->site->slug),
            ] : null),
        ];
    }
}
