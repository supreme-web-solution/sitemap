<?php

namespace App\Http\Requests\Leads;

use App\Enums\LeadStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Arr;

class LeadBoardRequest extends LeadFilterRequest
{
    /**
     * The most cards one column may load.
     */
    public const MAX_CARDS_PER_COLUMN = 200;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...Arr::except(parent::rules(), ['status']),
            'limits' => ['nullable', 'array'],
            'limits.*' => ['integer', 'min:1', 'max:'.self::MAX_CARDS_PER_COLUMN],
        ];
    }

    /**
     * Get the validated filters with absent values removed.
     *
     * @return array{search?: string, sort?: string, tier?: string, follow_up?: string, assigned?: string, ideal?: string}
     */
    public function filters(): array
    {
        return Arr::except(parent::filters(), ['limits']);
    }

    /**
     * How many cards each column should load, keyed by status value.
     *
     * @return array<string, int>
     */
    public function limits(): array
    {
        $limits = (array) ($this->validated()['limits'] ?? []);
        $valid = array_map(fn (LeadStatus $status) => $status->value, LeadStatus::cases());

        return collect($limits)
            ->only($valid)
            ->map(fn (mixed $limit) => (int) $limit)
            ->all();
    }
}
