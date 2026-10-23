<?php

namespace App\Http\Requests\Leads;

use App\Enums\LeadStatus;
use App\Enums\LostReason;
use App\Models\Team;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateLeadRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Team $team */
        $team = $this->route('current_team');

        return [
            'status' => ['sometimes', 'required', Rule::enum(LeadStatus::class)],
            'lost_reason' => ['exclude_unless:status,lost', 'required', Rule::enum(LostReason::class)],
            'assigned_user_id' => ['sometimes', 'nullable', 'integer', Rule::exists('team_members', 'user_id')->where('team_id', $team->id)],
            'deal_value' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:10000000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'next_follow_up_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
