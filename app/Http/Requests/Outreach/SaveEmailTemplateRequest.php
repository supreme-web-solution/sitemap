<?php

namespace App\Http\Requests\Outreach;

use App\Services\Outreach\MergeFieldRenderer;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveEmailTemplateRequest extends FormRequest
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
        $knownFields = function (string $attribute, mixed $value, Closure $fail): void {
            $unknown = app(MergeFieldRenderer::class)->unknownFields((string) $value);

            if ($unknown !== []) {
                $fail(__('Unknown merge fields: :fields.', ['fields' => implode(', ', $unknown)]));
            }
        };

        return [
            'name' => ['required', 'string', 'max:100'],
            'subject' => ['required', 'string', 'max:200', $knownFields],
            'body' => ['required', 'string', 'max:5000', $knownFields],
        ];
    }
}
