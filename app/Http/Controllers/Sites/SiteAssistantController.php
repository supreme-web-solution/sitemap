<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sites\UpdateSiteAssistantRequest;
use App\Models\Site;
use App\Models\Team;
use App\Services\Assistant\AssistantProvisioner;
use App\Services\SiteBuilder\SiteCache;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class SiteAssistantController extends Controller
{
    /**
     * Save the assistant's settings. Switching it on for the first time writes its knowledge base.
     */
    public function update(UpdateSiteAssistantRequest $request, Team $currentTeam, Site $site, AssistantProvisioner $provisioner): RedirectResponse
    {
        $assistant = $provisioner->for($site);
        $data = $request->validated();

        $assistant->forceFill([
            'enabled' => $data['enabled'],
            'name' => trim($data['name']),
            'avatar' => $data['avatar'],
            'voice' => $data['voice'],
            'greeting' => filled($data['greeting'] ?? null) ? trim($data['greeting']) : null,
            'knowledge' => filled($data['knowledge'] ?? null) ? trim($data['knowledge']) : null,
        ])->save();

        if ($assistant->enabled && blank($assistant->knowledge)) {
            $assistant->setRelation('site', $site);
            $provisioner->refreshKnowledge($assistant);
        }

        SiteCache::flush($site->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => $assistant->enabled
            ? __('Assistant saved and switched on.')
            : __('Assistant saved. It is switched off, so visitors will not see it.')]);

        return back();
    }

    /**
     * Rewrite the knowledge base from the website and the Google listing, replacing any edits.
     */
    public function knowledge(Team $currentTeam, Site $site, AssistantProvisioner $provisioner): RedirectResponse
    {
        $assistant = $provisioner->for($site);
        $assistant->setRelation('site', $site);

        $provisioner->refreshKnowledge($assistant);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Knowledge base refreshed from the website.')]);

        return back();
    }
}
