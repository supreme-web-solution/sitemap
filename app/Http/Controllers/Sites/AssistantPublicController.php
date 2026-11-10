<?php

namespace App\Http\Controllers\Sites;

use App\Actions\Sites\RecordSiteContact;
use App\Http\Controllers\Controller;
use App\Models\SiteAssistant;
use App\Services\Assistant\AssistantProvisioner;
use App\Services\Assistant\AssistantResponder;
use App\Services\Assistant\AssistantSpeech;
use App\Services\Assistant\AssistantVoices;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * The public endpoints the website assistant widget talks to from a visitor's browser.
 */
class AssistantPublicController extends Controller
{
    /**
     * Get what the widget needs to show itself.
     */
    public function show(string $key, AssistantProvisioner $provisioner, AssistantVoices $voices): JsonResponse
    {
        $assistant = $this->enabledAssistant($key);
        $site = $assistant->site->loadMissing('lead');

        return response()->json([
            'name' => $assistant->name,
            'business' => $site->lead->name,
            'greeting' => filled($assistant->greeting)
                ? $assistant->greeting
                : $provisioner->defaultGreeting($assistant, $site->lead->name),
            'voice_id' => $assistant->voice_id,
            'voice_on' => $voices->isSpoken($assistant->voice_id),
            'presenter' => [
                'name' => $assistant->presenter_name,
                'gender' => $assistant->presenter_gender,
                'image_url' => $assistant->presenter_thumbnail_url,
            ],
            'accent' => is_string($site->content['accent'] ?? null) ? $site->content['accent'] : '#2563eb',
        ])->header('Cache-Control', 'public, max-age=60');
    }

    /**
     * Answer one visitor message, with spoken audio when a voice is configured.
     */
    public function chat(Request $request, string $key, AssistantResponder $responder): JsonResponse
    {
        $assistant = $this->enabledAssistant($key);

        $data = $request->validate([
            'message' => ['required', 'string', 'max:'.(int) config('assistant.max_message_length')],
            'history' => ['nullable', 'array', 'max:40'],
            'history.*.role' => ['required_with:history', 'string'],
            'history.*.content' => ['required_with:history', 'string', 'max:2000'],
        ]);

        return response()->json($responder->reply($assistant, $data['message'], $data['history'] ?? []));
    }

    /**
     * Speak a short line (the greeting) without asking the language model.
     */
    public function speak(Request $request, string $key, AssistantSpeech $speech): JsonResponse
    {
        $assistant = $this->enabledAssistant($key);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:'.AssistantSpeech::MAX_LENGTH],
        ]);

        $spoken = $speech->speak($data['text'], $assistant->voice_id);

        return response()->json([
            'audio' => $spoken['base64'] ?? null,
            'audio_mime' => $spoken['mime'] ?? null,
        ]);
    }

    /**
     * Pass a visitor's details to the business, the same way the contact form does.
     */
    public function lead(Request $request, string $key, RecordSiteContact $record, AssistantResponder $responder): JsonResponse
    {
        $assistant = $this->enabledAssistant($key);

        $input = [
            'name' => $request->input('name'),
            'contact' => $request->input('contact'),
            'message' => $this->leadMessage($request, $responder),
            'website' => $request->input('website'),
            'company_url' => $request->input('company_url'),
        ];

        if ($record->handle($assistant->site, $input)) {
            $assistant->newQuery()->whereKey($assistant->id)->increment('leads_count');
        }

        return response()->json(['ok' => true]);
    }

    protected function enabledAssistant(string $key): SiteAssistant
    {
        $assistant = SiteAssistant::query()
            ->where('public_key', $key)
            ->where('enabled', true)
            ->with('site.lead', 'site.team')
            ->first();

        abort_if($assistant === null || ! $assistant->hasPresenter(), 404);

        return $assistant;
    }

    /**
     * What the visitor wanted, with the last few turns of the conversation for context.
     */
    protected function leadMessage(Request $request, AssistantResponder $responder): string
    {
        $note = trim((string) $request->input('message', ''));
        $history = $request->input('history');
        $turns = $responder->cleanHistory(is_array($history) ? $history : []);

        $lines = [$note !== '' ? $note : __('Asked to be contacted via the website assistant.')];

        if ($turns !== []) {
            $lines[] = '';
            $lines[] = __('Conversation with the website assistant:');

            foreach ($turns as $turn) {
                $lines[] = ($turn['role'] === 'user' ? __('Visitor') : __('Assistant')).': '.$turn['content'];
            }
        }

        return Str::limit(implode("\n", $lines), 1900, '');
    }
}
