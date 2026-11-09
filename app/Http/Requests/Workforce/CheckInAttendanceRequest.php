<?php

namespace App\Http\Requests\Workforce;

use App\Enums\VerificationMethod;
use Illuminate\Validation\Rule;

class CheckInAttendanceRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('attendance.record') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'methods' => ['required', 'array', 'min:1'],
            'methods.*' => ['required', Rule::enum(VerificationMethod::class)],
            'token' => ['nullable', 'string', 'max:128'],
            'beacon_public_id' => ['nullable', 'string', 'max:120'],
            'rssi' => ['nullable', 'integer', 'between:-120,0'],
            'bssid' => ['nullable', 'string', 'max:32'],
            'otp' => ['nullable', 'string', 'max:12'],
            'device_hash' => ['nullable', 'string', 'max:128'],
            'location_uuid' => ['nullable', 'uuid', $this->tenantExists('locations')],
        ] + $this->coordinateRules(false);
    }
}
