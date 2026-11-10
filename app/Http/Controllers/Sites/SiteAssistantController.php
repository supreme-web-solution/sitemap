<?php

namespace App\Http\Controllers\Sites;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sites\PreviewAssistantVoiceRequest;
use App\Http\Requests\Sites\UpdateSiteAssistantRequest;
use App\Models\Site;
use App\Models\Team;
use App\Services\Assistant\AssistantProvisioner;
use App\Services\Assistant\AssistantSpeech;
use App\Services\Assistant\AssistantVoices;
use App\Services\Assistant\TalkingAvatarGate;
use App\Services\Did\DidClient;
use App\Services\Simli\SimliFaces;
use App\Services\SiteBuilder\SiteCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use RuntimeException;

class SiteAssistantController extends Controller
{
    /**
     * Save the assistant's settings. Switching it on for the first time writes its knowledge base.
     */
    public function update(
        UpdateSiteAssistantRequest $request,
        Team $currentTeam,
        Site $site,
        AssistantProvisioner $provisioner,
        DidClient $did,
        AssistantVoices $voices,
        TalkingAvatarGate $talking,
        SimliFaces $faces,
    ): RedirectResponse {
        $assistant = $provisioner->for($site);
        $data = $request->validated();
        $voice = $voices->find($data['voice_id']);
        $presenter = filled($data['presenter_id'] ?? null)
            ? $this->findPresenter($did, (string) $data['presenter_id'])
            : null;

        $wantTalking = (bool) ($data['talking_avatar_enabled'] ?? false);
        $face = filled($data['simli_face_id'] ?? null)
            ? $faces->find((string) $data['simli_face_id'])
            : null;

        $assistant->forceFill([
            'enabled' => $data['enabled'],
            'name' => trim($data['name']),
            'presenter_id' => $presenter['id'] ?? null,
            'presenter_name' => $presenter['name'] ?? null,
            'presenter_gender' => $presenter['gender'] ?? null,
            'presenter_thumbnail_url' => $presenter['thumbnail_url'] ?? null,
            'voice_id' => $voice['id'],
            'voice_name' => $voice['name'],
            'talking_avatar_enabled' => $talking->available() && $wantTalking && $face !== null,
            'simli_face_id' => $face['id'] ?? null,
            'simli_face_name' => $face['name'] ?? null,
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

    /**
     * Play a short sample of a voice in the settings panel.
     */
    public function previewVoice(PreviewAssistantVoiceRequest $request, Team $currentTeam, Site $site, AssistantSpeech $speech): JsonResponse
    {
        $url = $speech->previewUrl($request->validated('voice_id'));

        if ($url === null) {
            throw ValidationException::withMessages([
                'voice_id' => __('Voice preview is not available. Check OPENAI_API_KEY.'),
            ]);
        }

        return response()->json(['url' => $url]);
    }

    /**
     * @return array{id: string, name: string, gender: string|null, thumbnail_url: string|null}
     */
    protected function findPresenter(DidClient $did, string $id): array
    {
        if (! $did->configured()) {
            throw ValidationException::withMessages([
                'presenter_id' => __('Add DID_API_KEY to choose a photoreal presenter.'),
            ]);
        }

        try {
            $presenter = collect($did->presenters())->firstWhere('id', $id);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['presenter_id' => $exception->getMessage()]);
        }

        return $presenter ?? throw ValidationException::withMessages([
            'presenter_id' => __('That presenter is no longer available. Choose another.'),
        ]);
    }
}
