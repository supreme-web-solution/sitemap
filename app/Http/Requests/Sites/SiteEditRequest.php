<?php

namespace App\Http\Requests\Sites;

use App\Services\SiteBuilder\SiteEditApplier;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SiteEditRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'revision' => ['required', 'integer', 'min:0'],
            'ops' => ['required', 'array', 'min:1', 'max:'.SiteEditApplier::MAX_OPERATIONS],
            'ops.*' => ['required', 'array'],
            'ops.*.op' => ['required', 'in:set,insert,remove,move'],
            'ops.*.path' => ['required', 'string', 'max:80'],
            'ops.*.value' => ['nullable'],
            'ops.*.index' => ['nullable', 'integer', 'min:0'],
            'ops.*.from' => ['nullable', 'integer', 'min:0'],
            'ops.*.to' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
