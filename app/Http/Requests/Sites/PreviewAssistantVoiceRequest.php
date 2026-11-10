<?php

namespace App\Http\Requests\Sites;

use App\Services\Assistant\AssistantVoices;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewAssistantVoiceRequest extends FormRequest
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
            'voice_id' => ['required', 'string', Rule::in(array_column(app(AssistantVoices::class)->all(), 'id'))],
        ];
    }
}
