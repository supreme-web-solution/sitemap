<?php

namespace App\Http\Requests\Outreach;

use Illuminate\Foundation\Http\FormRequest;

class GenerateFollowUpSequenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prompt' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
