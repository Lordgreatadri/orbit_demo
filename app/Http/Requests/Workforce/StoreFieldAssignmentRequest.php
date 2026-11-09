<?php

namespace App\Http\Requests\Workforce;

class StoreFieldAssignmentRequest extends WorkforceRequest
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
            'location_uuid' => ['required', 'uuid', $this->tenantExists('locations')],
            'title' => ['required', 'string', 'max:160'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ];
    }
}
