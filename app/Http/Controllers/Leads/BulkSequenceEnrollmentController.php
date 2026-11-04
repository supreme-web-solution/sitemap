<?php

namespace App\Http\Controllers\Leads;

use App\Http\Controllers\Controller;
use App\Http\Requests\Leads\BulkEnrollLeadsRequest;
use App\Models\FollowUpSequence;
use App\Models\Lead;
use App\Models\Team;
use App\Services\Outreach\SequenceEnroller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class BulkSequenceEnrollmentController extends Controller
{
    public function __construct(public SequenceEnroller $enroller) {}

    /**
     * Enroll many leads in the same follow-up sequence.
     */
    public function store(BulkEnrollLeadsRequest $request, Team $currentTeam): RedirectResponse
    {
        $data = $request->validated();
        $sequence = FollowUpSequence::query()->findOrFail($data['sequence_id']);

        $leads = Lead::query()
            ->where('team_id', $currentTeam->id)
            ->whereIn('id', $data['lead_ids'])
            ->get();

        $enrolled = 0;
        $skipped = 0;

        foreach ($leads as $lead) {
            if ($this->enroller->enrollOrSkip($currentTeam, $lead, $sequence) === null) {
                $enrolled++;
            } else {
                $skipped++;
            }
        }

        if ($enrolled > 0) {
            $message = trans_choice(
                'Enrolled :count lead in :name.|Enrolled :count leads in :name.',
                $enrolled,
                ['count' => $enrolled, 'name' => $sequence->name],
            );

            if ($skipped > 0) {
                $message .= ' '.trans_choice(
                    ':count skipped (missing email, won/lost, or other issue).|:count skipped (missing email, won/lost, or other issues).',
                    $skipped,
                    ['count' => $skipped],
                );
            }

            Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
        } else {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('No leads were enrolled. Add email addresses and check that leads are not won or lost.'),
            ]);
        }

        return back();
    }
}
