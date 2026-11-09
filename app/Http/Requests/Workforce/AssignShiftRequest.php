<?php

namespace App\Http\Requests\Workforce;

class AssignShiftRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('schedules.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'staff_uuid' => ['required', 'uuid', $this->tenantExists('staff_members')],
            'work_date' => ['required', 'date'],
        ];
    }
}
