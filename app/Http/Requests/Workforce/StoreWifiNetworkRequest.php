<?php

namespace App\Http\Requests\Workforce;

use App\Models\WifiNetwork;

class StoreWifiNetworkRequest extends WorkforceRequest
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
            'label' => ['required', 'string', 'max:120'],
            'bssid' => ['required', 'string', 'max:32'],
            'location_uuid' => ['required', 'uuid', $this->tenantExists('locations')],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if (WifiNetwork::normalizeBssid((string) $this->input('bssid')) === '') {
                $validator->errors()->add('bssid', 'Enter a 12-digit BSSID, for example AA:BB:CC:DD:EE:FF.');
            }
        });
    }

    public function normalizedBssid(): string
    {
        return WifiNetwork::normalizeBssid((string) $this->validated('bssid'));
    }
}
