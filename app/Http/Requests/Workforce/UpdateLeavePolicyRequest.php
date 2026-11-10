<?php

namespace App\Http\Requests\Workforce;

use App\Enums\LeaveApprovalMode;
use App\Enums\LeaveCountMode;
use Illuminate\Validation\Rule;

class UpdateLeavePolicyRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('leave.policy') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $days = array_map('intval', (array) $this->input('weekend_days', []));

        $this->merge([
            'weekend_days' => $days,
            'exclude_non_working' => $this->boolean('exclude_non_working'),
            'allow_self_approval' => $this->boolean('allow_self_approval'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'year_start_month' => ['required', 'integer', 'between:1,12'],
            'year_start_day' => ['required', 'integer', 'between:1,31'],
            'count_mode' => ['required', Rule::enum(LeaveCountMode::class)],
            'weekend_days' => ['array'],
            'weekend_days.*' => ['integer', 'between:1,7'],
            'exclude_non_working' => ['boolean'],
            'approval_mode' => ['required', Rule::enum(LeaveApprovalMode::class)],
            'allow_self_approval' => ['boolean'],
        ];
    }
}
