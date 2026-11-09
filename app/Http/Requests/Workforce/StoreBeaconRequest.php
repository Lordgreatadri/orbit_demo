<?php

namespace App\Http\Requests\Workforce;

class StoreBeaconRequest extends WorkforceRequest
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
            'name' => ['required', 'string', 'max:120'],
            'public_id' => ['required', 'string', 'max:120'],
            'location_uuid' => ['required', 'uuid', $this->tenantExists('locations')],
        ];
    }
}
