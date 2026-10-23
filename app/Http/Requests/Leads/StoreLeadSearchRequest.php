<?php

namespace App\Http\Requests\Leads;

use App\Enums\WebsiteFilter;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLeadSearchRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['required', 'string', 'max:100'],
            'location' => ['required', 'string', 'max:150'],
            'locations' => ['nullable', 'array', 'max:9'],
            'locations.*' => ['string', 'max:150'],
            'website_filter' => ['required', Rule::enum(WebsiteFilter::class)],
            'minimum_rating' => ['nullable', 'numeric', Rule::in([2, 2.5, 3, 3.5, 4, 4.5])],
            'max_results' => ['required', 'integer', 'min:1', 'max:100'],
        ];
    }
}
