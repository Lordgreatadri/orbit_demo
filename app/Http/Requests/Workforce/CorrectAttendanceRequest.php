<?php

namespace App\Http\Requests\Workforce;

use App\Enums\AttendanceStatus;
use Illuminate\Validation\Rule;

class CorrectAttendanceRequest extends WorkforceRequest
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
            'work_date' => ['required', 'date'],
            'status' => ['required', Rule::enum(AttendanceStatus::class)],
            'check_in_at' => ['nullable', 'date'],
            'check_out_at' => ['nullable', 'date'],
            'location_uuid' => ['nullable', 'uuid', $this->tenantExists('locations')],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
