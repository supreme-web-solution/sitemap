<?php

namespace App\Http\Requests\Proposals;

use App\Enums\BillingInterval;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePackageRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0.01', 'max:999999.99', 'decimal:0,2'],
            'interval' => ['nullable', Rule::enum(BillingInterval::class)],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * The attributes to store.
     *
     * @return array<string, mixed>
     */
    public function packageAttributes(): array
    {
        return [
            'name' => $this->validated('name'),
            'description' => $this->validated('description'),
            'unit_cents' => (int) round(((float) $this->validated('price')) * 100),
            'interval' => $this->validated('interval'),
            'is_active' => (bool) ($this->validated('is_active') ?? true),
        ];
    }
}
