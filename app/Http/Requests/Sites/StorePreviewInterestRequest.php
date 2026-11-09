<?php

namespace App\Http\Requests\Sites;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePreviewInterestRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make the request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'intent' => ['nullable', Rule::in(['discuss', 'claim'])],
            'name' => ['nullable', 'string', 'max:100'],
            'contact' => ['required', 'string', 'max:150'],
            'message' => ['nullable', 'string', 'max:1000'],
            'company_url' => ['nullable', 'string', 'max:200'],
        ];
    }
}
