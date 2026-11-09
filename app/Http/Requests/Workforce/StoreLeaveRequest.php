<?php

namespace App\Http\Requests\Workforce;

class StoreLeaveRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('leave.request') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'leave_type_uuid' => ['required', 'uuid', $this->tenantExists('leave_types')->where('is_active', true)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'max:1000'],
            'document' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:2048'],
        ];
    }
}
