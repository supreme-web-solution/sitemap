<?php

namespace App\Http\Requests\Leads;

use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkDeleteLeadsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to this request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('current_team');

        return [
            'lead_ids' => ['required', 'array', 'min:1', 'max:100'],
            'lead_ids.*' => ['integer', Rule::exists('leads', 'id')->where('team_id', $team->id)],
        ];
    }
}
