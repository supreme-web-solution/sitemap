<?php

namespace App\Http\Controllers\Sites;

use App\Enums\LeadActivityType;
use App\Enums\LeadStatus;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Proposal;
use App\Models\Site;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreviewPackageController extends Controller
{
    /**
     * Record that the prospect picked a package from the public preview.
     */
    public function store(Request $request, Site $site): RedirectResponse
    {
        $thanks = to_route('sites.preview', ['site' => $site->slug, 'thanks' => 1, 'internal' => 1]);

        $data = $request->validate([
            'package_id' => ['required', 'integer', Rule::exists('packages', 'id')->where('team_id', $site->team_id)->where('is_active', true)],
            'company_url' => ['nullable', 'string', 'max:200'],
        ]);

        if (filled($data['company_url'] ?? null)) {
            return $thanks;
        }

        $package = Package::query()->findOrFail($data['package_id']);
        $lead = $site->lead;

        $proposal = $site->team->proposals()->make([
            'lead_id' => $lead->id,
            'title' => $package->name,
        ]);
        $proposal->forceFill([
            'number' => Proposal::nextNumber($site->team),
            'status' => ProposalStatus::Sent,
            'sent_at' => now(),
            'valid_until' => now()->addDays(14),
        ])->save();
        $proposal->items()->create([
            'description' => $package->name,
            'quantity' => 1,
            'unit_cents' => $package->unit_cents,
            'interval' => $package->interval,
        ]);

        $lead->recordActivity(
            LeadActivityType::ProposalSent,
            __('Chose :package from the preview', ['package' => $package->name]),
            ['package_id' => $package->id, 'proposal_id' => $proposal->id],
        );

        if (in_array($lead->status, [LeadStatus::New, LeadStatus::SiteGenerated, LeadStatus::Contacted], true)) {
            $lead->status = LeadStatus::Interested;
        }

        $lead->next_follow_up_at = today()->addDay();
        $lead->save();

        return $thanks;
    }
}
