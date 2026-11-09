<?php

namespace App\Http\Requests\Workforce;

class StoreAttendanceSessionRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendance.manage') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'require_location' => $this->boolean('require_location'),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'location_uuid' => ['nullable', 'uuid', $this->tenantExists('locations')],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'qr_ttl_seconds' => ['nullable', 'integer', 'between:10,120'],
            'require_location' => ['boolean'],
        ];
    }
}
