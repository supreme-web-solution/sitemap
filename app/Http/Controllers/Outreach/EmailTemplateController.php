<?php

namespace App\Http\Controllers\Outreach;

use App\Http\Controllers\Controller;
use App\Http\Requests\Outreach\SaveEmailTemplateRequest;
use App\Models\EmailTemplate;
use App\Models\Team;
use App\Services\Outreach\DefaultEmailTemplates;
use App\Services\Outreach\MergeFieldRenderer;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class EmailTemplateController extends Controller
{
    /**
     * Show the team's email templates.
     */
    public function index(Team $currentTeam, DefaultEmailTemplates $defaults): Response
    {
        $defaults->ensureFor($currentTeam);

        return Inertia::render('outreach/Templates', [
            'templates' => $currentTeam->emailTemplates()
                ->orderBy('id')
                ->get(['id', 'name', 'subject', 'body']),
            'mergeFields' => collect(MergeFieldRenderer::FIELDS)
                ->map(fn (string $description, string $key) => ['key' => $key, 'description' => $description])
                ->values(),
        ]);
    }

    /**
     * Create an email template.
     */
    public function store(SaveEmailTemplateRequest $request, Team $currentTeam): RedirectResponse
    {
        $currentTeam->emailTemplates()->create($request->validated());

        return back();
    }

    /**
     * Update an email template.
     */
    public function update(SaveEmailTemplateRequest $request, Team $currentTeam, EmailTemplate $emailTemplate): RedirectResponse
    {
        $emailTemplate->update($request->validated());

        return back();
    }

    /**
     * Delete an email template.
     */
    public function destroy(Team $currentTeam, EmailTemplate $emailTemplate): RedirectResponse
    {
        $emailTemplate->delete();

        return back();
    }
}
