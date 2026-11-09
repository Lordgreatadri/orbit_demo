<?php

namespace App\Http\Requests\Workforce;

use App\Enums\AttendanceStatus;
use Illuminate\Validation\Rule;

class FilterAttendanceRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendance.view') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date'],
            'status' => ['nullable', Rule::enum(AttendanceStatus::class)],
            'department' => ['nullable', 'uuid', $this->tenantExists('departments')],
        ];
    }
}
