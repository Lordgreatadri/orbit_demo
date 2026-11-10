<?php

namespace App\Http\Requests\Workforce;

class AdjustLeaveBalanceRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('leave.policy') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'staff_uuid' => ['required', 'uuid', $this->tenantExists('staff_members')],
            'leave_type_uuid' => ['required', 'uuid', $this->tenantExists('leave_types')],
            'days' => ['required', 'integer', 'between:-365,365', 'not_in:0'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }
}
