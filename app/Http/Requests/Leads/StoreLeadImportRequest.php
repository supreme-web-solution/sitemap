<?php

namespace App\Http\Requests\Leads;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLeadImportRequest extends FormRequest
{
    /**
     * Accepts Google Maps links (full or shortened) and raw place IDs.
     */
    public const MAPS_LINK_PATTERN = '/^(?:https?:\/\/(?:www\.)?(?:google\.[a-z.]+\/maps|maps\.google\.[a-z.]+|maps\.app\.goo\.gl|goo\.gl\/maps)\S*|ChIJ[\w-]+)$/i';

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['maps_url' => trim((string) $this->input('maps_url'))]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'maps_url' => ['required', 'string', 'max:2000', 'regex:'.self::MAPS_LINK_PATTERN],
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
            'maps_url.regex' => __('Paste a Google Maps link or place ID.'),
        ];
    }
}
