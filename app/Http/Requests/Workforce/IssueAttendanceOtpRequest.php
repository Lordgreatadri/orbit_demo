<?php

namespace App\Http\Requests\Workforce;

class IssueAttendanceOtpRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendance.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'staff_uuid' => ['required', 'uuid', $this->tenantExists('staff_members')],
        ];
    }
}
