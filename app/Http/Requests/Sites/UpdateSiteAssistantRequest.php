<?php

namespace App\Http\Requests\Sites;

use App\Models\SiteAssistant;
use App\Services\Assistant\AssistantVoices;
use App\Services\Assistant\TalkingAvatarGate;
use App\Services\Simli\SimliFaces;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSiteAssistantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return void
     */
    protected function prepareForValidation()
    {
        if ($this->input('simli_face_id') === '') {
            $this->merge(['simli_face_id' => null]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $talkingAvailable = app(TalkingAvatarGate::class)->available();
        $faceIds = array_column(app(SimliFaces::class)->all(), 'id');
        $liveMode = fn (): bool => $talkingAvailable && $this->boolean('talking_avatar_enabled');

        return [
            'enabled' => ['required', 'boolean'],
            'name' => ['required', 'string', 'max:60'],
            'presenter_id' => [
                Rule::requiredIf(fn () => $this->boolean('enabled') && ! $liveMode()),
                'nullable',
                'string',
                'max:191',
            ],
            'voice_id' => ['required', 'string', Rule::in(array_column(app(AssistantVoices::class)->all(), 'id'))],
            'talking_avatar_enabled' => ['sometimes', 'boolean'],
            'simli_face_id' => array_values(array_filter([
                Rule::requiredIf(fn () => $talkingAvailable && $this->boolean('talking_avatar_enabled')),
                'nullable',
                'string',
                $talkingAvailable ? Rule::in($faceIds) : null,
            ])),
            'launcher_side' => ['required', 'string', Rule::in(SiteAssistant::LAUNCHER_SIDES)],
            'greeting' => ['nullable', 'string', 'max:300'],
            'knowledge' => ['nullable', 'string', 'max:'.SiteAssistant::MAX_KNOWLEDGE_LENGTH],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => __('Give the assistant a name.'),
            'presenter_id.required' => __('Choose a presenter before switching the assistant on.'),
            'voice_id.in' => __('Choose one of the voices.'),
            'simli_face_id.required' => __('Choose a talking face before enabling the live avatar.'),
            'simli_face_id.in' => __('Choose one of the talking faces.'),
            'knowledge.max' => __('The knowledge base is too long. Keep it under :max characters.'),
        ];
    }
}
