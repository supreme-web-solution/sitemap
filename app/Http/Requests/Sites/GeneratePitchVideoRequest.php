<?php

namespace App\Http\Requests\Sites;

use App\Services\PitchVideos\PitchScriptGenerator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class GeneratePitchVideoRequest extends FormRequest
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
            'presenter_id' => ['required', 'string', 'max:191'],
            'voice_id' => ['required', 'string', 'max:191'],
            'script' => ['required', 'string', 'min:30', 'max:'.PitchScriptGenerator::MAX_LENGTH],
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
            'presenter_id.required' => __('Choose a presenter.'),
            'voice_id.required' => __('Choose a voice.'),
            'script.required' => __('Write the script first, or let the AI write it.'),
            'script.min' => __('The script is too short to make a video.'),
            'script.max' => __('The script is too long. Keep it under :max characters.'),
        ];
    }
}
