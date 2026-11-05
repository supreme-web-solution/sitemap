<?php

namespace App\Http\Controllers\Automation;

use App\Data\TeamAutomationSettings;
use App\Http\Controllers\Controller;
use App\Models\FollowUpSequence;
use App\Models\Package;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AutomationSettingsController extends Controller
{
    /**
     * Background workflow automation (set once, runs on triggers + scheduler).
     */
    public function index(Team $currentTeam): Response
    {
        $settings = $currentTeam->automationSettings();

        return Inertia::render('automations/Index', [
            'settings' => $settings->toArray(),
            'sequences' => FollowUpSequence::query()
                ->where('team_id', $currentTeam->id)
                ->orderBy('name')
                ->get(['id', 'name', 'is_active'])
                ->map(fn (FollowUpSequence $sequence) => [
                    'id' => $sequence->id,
                    'name' => $sequence->name,
                    'isActive' => $sequence->is_active,
                ]),
            'packages' => Package::query()
                ->where('team_id', $currentTeam->id)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Package $package) => [
                    'id' => $package->id,
                    'name' => $package->name,
                ]),
            'recentRuns' => $currentTeam->automationRuns()
                ->with('lead:id,name')
                ->latest('id')
                ->limit(25)
                ->get()
                ->map(fn ($run) => [
                    'id' => $run->id,
                    'triggerLabel' => $run->triggerEnum()->label(),
                    'summary' => $run->summary,
                    'leadName' => $run->lead?->name,
                    'createdAt' => $run->created_at?->toISOString(),
                ]),
        ]);
    }

    public function update(Request $request, Team $currentTeam): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'default_sequence_id' => ['nullable', 'integer'],
            'enroll_sequence_on_lead_created' => ['required', 'boolean'],
            'audit_website_on_lead_created' => ['required', 'boolean'],
            'queue_site_on_lead_created' => ['required', 'boolean'],
            'draft_proposal_on_site_generated' => ['required', 'boolean'],
            'default_package_ids' => ['nullable', 'array', 'max:10'],
            'default_package_ids.*' => ['integer'],
            'move_interested_when_preview_in_outreach' => ['required', 'boolean'],
            'move_interested_on_proposal_sent' => ['required', 'boolean'],
            'onboarding_on_invoice_paid' => ['required', 'boolean'],
            'reaudit_websites_every_days' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        if (filled($data['default_sequence_id'] ?? null)) {
            $exists = FollowUpSequence::query()
                ->where('team_id', $currentTeam->id)
                ->whereKey($data['default_sequence_id'])
                ->exists();

            if (! $exists) {
                throw ValidationException::withMessages([
                    'default_sequence_id' => __('Pick a sequence from this team.'),
                ]);
            }
        }

        $packageIds = array_values(array_map(intval(...), $data['default_package_ids'] ?? []));

        if ($packageIds !== []) {
            $validCount = Package::query()
                ->where('team_id', $currentTeam->id)
                ->whereIn('id', $packageIds)
                ->count();

            if ($validCount !== count($packageIds)) {
                throw ValidationException::withMessages([
                    'default_package_ids' => __('One or more packages are invalid.'),
                ]);
            }
        }

        $currentTeam->forceFill([
            'automation' => TeamAutomationSettings::from($data)->toArray(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Automation settings saved.')]);

        return back();
    }
}
