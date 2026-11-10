<?php

namespace App\Http\Requests\Sites;

use App\Models\SiteAssistant;
use App\Services\Assistant\AssistantVoices;
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
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'name' => ['required', 'string', 'max:60'],
            'presenter_id' => [Rule::requiredIf(fn () => $this->boolean('enabled')), 'nullable', 'string', 'max:191'],
            'voice_id' => ['required', 'string', Rule::in(array_column(app(AssistantVoices::class)->all(), 'id'))],
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
            'knowledge.max' => __('The knowledge base is too long. Keep it under :max characters.'),
        ];
    }
}
