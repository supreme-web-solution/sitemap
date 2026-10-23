<?php

namespace App\Http\Controllers\Proposals;

use App\Enums\BillingInterval;
use App\Enums\ProposalStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Proposals\SaveProposalRequest;
use App\Models\Invoice;
use App\Models\Lead;
use App\Models\Package;
use App\Models\Proposal;
use App\Models\ProposalItem;
use App\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProposalController extends Controller
{
    /**
     * List the team's proposals.
     */
    public function index(Team $currentTeam): Response
    {
        return Inertia::render('proposals/Index', [
            'proposals' => $currentTeam->proposals()
                ->with(['lead:id,name', 'items'])
                ->latest('id')
                ->limit(200)
                ->get()
                ->map(fn (Proposal $proposal) => [
                    'id' => $proposal->id,
                    'number' => $proposal->number,
                    'title' => $proposal->title,
                    'leadName' => $proposal->lead->name,
                    'status' => $proposal->isExpired() && $proposal->status === ProposalStatus::Sent ? 'expired' : $proposal->status->value,
                    'statusLabel' => $proposal->isExpired() && $proposal->status === ProposalStatus::Sent ? 'Expired' : $proposal->status->label(),
                    'firstYear' => Invoice::formatCents($proposal->firstYearCents()),
                    'sentAt' => $proposal->sent_at?->toISOString(),
                ]),
        ]);
    }

    /**
     * Show the form for a new proposal.
     */
    public function create(Request $request, Team $currentTeam): Response
    {
        return Inertia::render('proposals/Edit', [
            'proposal' => null,
            ...$this->formData($currentTeam),
            'defaultLeadId' => $currentTeam->leads()->whereKey($request->integer('lead'))->value('id'),
        ]);
    }

    public function store(SaveProposalRequest $request, Team $currentTeam): RedirectResponse
    {
        $proposal = DB::transaction(function () use ($request, $currentTeam) {
            $proposal = $currentTeam->proposals()->make($request->safe()->only(['lead_id', 'title', 'intro', 'terms', 'valid_until']));
            $proposal->forceFill(['number' => Proposal::nextNumber($currentTeam), 'status' => ProposalStatus::Draft])->save();
            $proposal->items()->createMany($request->lineItems());

            return $proposal;
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Proposal :number saved as a draft.', ['number' => $proposal->number])]);

        return to_route('proposals.edit', ['current_team' => $currentTeam->slug, 'proposal' => $proposal->id]);
    }

    /**
     * Show a proposal: editable until the client accepts it.
     */
    public function edit(Team $currentTeam, Proposal $proposal): Response
    {
        $proposal->load(['items', 'lead:id,name,email']);

        return Inertia::render('proposals/Edit', [
            'proposal' => [
                'id' => $proposal->id,
                'number' => $proposal->number,
                'leadId' => $proposal->lead_id,
                'leadName' => $proposal->lead->name,
                'leadEmail' => $proposal->lead->email,
                'title' => $proposal->title,
                'intro' => $proposal->intro,
                'terms' => $proposal->terms,
                'validUntil' => $proposal->valid_until?->toDateString(),
                'status' => $proposal->status->value,
                'statusLabel' => $proposal->status->label(),
                'editable' => $proposal->status !== ProposalStatus::Accepted,
                'publicUrl' => $proposal->status === ProposalStatus::Draft ? null : $proposal->publicUrl(),
                'sentAt' => $proposal->sent_at?->toISOString(),
                'viewedAt' => $proposal->viewed_at?->toISOString(),
                'acceptedAt' => $proposal->accepted_at?->toISOString(),
                'acceptedName' => $proposal->accepted_name,
                'items' => $proposal->items->map(fn (ProposalItem $item) => [
                    'description' => $item->description,
                    'quantity' => $item->quantity,
                    'unitPrice' => $item->unit_cents / 100,
                    'interval' => $item->interval?->value ?? '',
                ])->values(),
            ],
            ...$this->formData($currentTeam),
            'defaultLeadId' => null,
        ]);
    }

    public function update(SaveProposalRequest $request, Team $currentTeam, Proposal $proposal): RedirectResponse
    {
        abort_if($proposal->status === ProposalStatus::Accepted, 403);

        DB::transaction(function () use ($request, $proposal) {
            $proposal->update($request->safe()->only(['title', 'intro', 'terms', 'valid_until']));
            $proposal->items()->delete();
            $proposal->items()->createMany($request->lineItems());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Proposal saved.')]);

        return back();
    }

    public function destroy(Team $currentTeam, Proposal $proposal): RedirectResponse
    {
        abort_unless($proposal->status === ProposalStatus::Draft, 403);

        $proposal->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Draft deleted.')]);

        return to_route('proposals.index', ['current_team' => $currentTeam->slug]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Team $team): array
    {
        return [
            'leads' => $team->leads()->orderBy('name')->limit(1000)->get(['id', 'name', 'email'])
                ->map(fn (Lead $lead) => ['id' => $lead->id, 'name' => $lead->name, 'email' => $lead->email])->all(),
            'packages' => $team->packages()->where('is_active', true)->orderBy('name')->get()
                ->map(fn (Package $package) => [
                    'id' => $package->id,
                    'name' => $package->name,
                    'description' => $package->description,
                    'unitPrice' => $package->unit_cents / 100,
                    'interval' => $package->interval?->value ?? '',
                ])->all(),
            'intervals' => collect(BillingInterval::cases())->map(fn (BillingInterval $interval) => ['value' => $interval->value, 'label' => $interval->label()])->values(),
            'today' => today()->toDateString(),
        ];
    }
}