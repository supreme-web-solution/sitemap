<?php

namespace App\Http\Requests\Leads;

use App\Enums\LeadStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LeadFilterRequest extends FormRequest
{
    /**
     * The sort orders the lead list supports.
     *
     * @var list<string>
     */
    public const SORTS = ['score', 'reviews', 'newest', 'follow_up'];

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
            'status' => ['nullable', Rule::enum(LeadStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'tier' => ['nullable', Rule::in(['hot', 'warm', 'cold'])],
            'follow_up' => ['nullable', Rule::in(['due'])],
            'assigned' => ['nullable', 'integer'],
            'ideal' => ['nullable', Rule::in(['1', 'true'])],
        ];
    }

    /**
     * Get the validated filters with absent values removed.
     *
     * @return array{status?: string, search?: string, sort?: string, tier?: string, follow_up?: string, assigned?: string, ideal?: string}
     */
    public function filters(): array
    {
        return array_filter($this->validated(), fn (mixed $value) => filled($value));
    }
}
