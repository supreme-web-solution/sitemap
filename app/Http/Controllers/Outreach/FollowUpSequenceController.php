<?php

namespace App\Http\Controllers\Outreach;

use App\Http\Controllers\Controller;
use App\Models\FollowUpSequence;
use App\Models\Team;
use App\Services\Outreach\DefaultFollowUpSequences;
use App\Services\Outreach\MergeFieldRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FollowUpSequenceController extends Controller
{
    /**
     * Show the team's automated email sequences.
     */
    public function index(Team $currentTeam, DefaultFollowUpSequences $defaults): Response
    {
        $defaults->ensureFor($currentTeam);

        return Inertia::render('sequences/Index', [
            'sequences' => $currentTeam->followUpSequences()->with('steps')->withCount('enrollments')->orderBy('id')->get()->map(fn (FollowUpSequence $sequence) => [
                'id' => $sequence->id,
                'name' => $sequence->name,
                'isActive' => $sequence->is_active,
                'enrollmentCount' => $sequence->enrollments_count,
                'steps' => $sequence->steps->map(fn ($step) => [
                    'id' => $step->id,
                    'position' => $step->position,
                    'delayDays' => $step->delay_days,
                    'subject' => $step->subject,
                    'body' => $step->body,
                ]),
            ]),
            'mergeFields' => collect(MergeFieldRenderer::FIELDS)->map(fn (string $description, string $key) => [
                'key' => $key,
                'description' => $description,
            ])->values(),
        ]);
    }

    /**
     * Create or replace a sequence and its steps.
     */
    public function store(Request $request, Team $currentTeam): RedirectResponse
    {
        $data = $this->validated($request);
        $sequence = $currentTeam->followUpSequences()->create([
            'name' => $data['name'],
            'is_active' => $data['is_active'],
        ]);
        $this->syncSteps($sequence, $data['steps']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sequence saved.')]);

        return back();
    }

    /**
     * Update a sequence and replace its steps.
     */
    public function update(Request $request, Team $currentTeam, FollowUpSequence $followUpSequence): RedirectResponse
    {
        $data = $this->validated($request);
        $followUpSequence->update(['name' => $data['name'], 'is_active' => $data['is_active']]);
        $followUpSequence->steps()->delete();
        $this->syncSteps($followUpSequence, $data['steps']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sequence saved.')]);

        return back();
    }

    /**
     * Delete a sequence. Enrollments are removed with it.
     */
    public function destroy(Team $currentTeam, FollowUpSequence $followUpSequence): RedirectResponse
    {
        $followUpSequence->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Sequence deleted.')]);

        return back();
    }

    /**
     * @return array{name: string, is_active: bool, steps: list<array{delay_days: int, subject: string, body: string}>}
     */
    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'is_active' => ['required', 'boolean'],
            'steps' => ['required', 'array', 'min:1', 'max:10'],
            'steps.*.delay_days' => ['required', 'integer', 'min:0', 'max:60'],
            'steps.*.subject' => ['required', 'string', 'max:150'],
            'steps.*.body' => ['required', 'string', 'max:5000'],
        ]);
    }

    /**
     * @param  list<array{delay_days: int, subject: string, body: string}>  $steps
     */
    protected function syncSteps(FollowUpSequence $sequence, array $steps): void
    {
        foreach (array_values($steps) as $position => $step) {
            $sequence->steps()->create([
                'position' => $position,
                'delay_days' => $step['delay_days'],
                'subject' => $step['subject'],
                'body' => $step['body'],
            ]);
        }
    }
}
