<?php

namespace App\Http\Requests\Workforce;

class StoreLeaveRuleRequest extends WorkforceRequest
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
            'leave_type_uuid' => ['required', 'uuid', $this->tenantExists('leave_types')],
            'priority' => ['required', 'integer', 'min:1', 'max:1000'],
            'entitled_days' => ['required', 'integer', 'min:0', 'max:365'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'staff_category_uuid' => ['nullable', 'uuid', $this->tenantExists('staff_categories')],
            'department_uuid' => ['nullable', 'uuid', $this->tenantExists('departments')],
            'grade' => ['nullable', 'string', 'max:40'],
            'employment_type' => ['nullable', 'string', 'max:40'],
            'min_service_months' => ['nullable', 'integer', 'min:0', 'max:600'],
            'max_service_months' => ['nullable', 'integer', 'min:0', 'max:600'],
        ];
    }
}
