<?php

namespace App\Http\Requests\Admin;

use App\Enums\PlanFeature;
use App\Enums\UsageMetric;
use App\Models\Plan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SavePlanRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        $plan = $this->route('plan');

        return [
            'name' => ['required', 'string', 'max:80'],
            'slug' => ['required', 'string', 'max:80', 'alpha_dash', Rule::unique(Plan::class)->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'limits' => ['array'],
            'limits.*' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'features' => ['array'],
            'features.*' => [Rule::enum(PlanFeature::class)],
        ];
    }

    /**
     * Get the plan attributes: only known metrics are kept, blank means unlimited.
     *
     * @return array<string, mixed>
     */
    public function planAttributes(): array
    {
        $validated = $this->validated();

        $limits = [];

        foreach (UsageMetric::cases() as $metric) {
            $value = $validated['limits'][$metric->value] ?? null;
            $limits[$metric->value] = $value === null || $value === '' ? null : (int) $value;
        }

        return [
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'description' => $validated['description'] ?? null,
            'price_cents' => (int) round($validated['price'] * 100),
            'is_default' => (bool) ($validated['is_default'] ?? false),
            'is_active' => (bool) ($validated['is_active'] ?? true),
            'limits' => $limits,
            'features' => array_values(array_unique($validated['features'] ?? [])),
        ];
    }
}
