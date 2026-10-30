<?php

namespace App\Http\Requests\Structure;

use App\Enums\LocationType;
use App\Models\Location;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class LocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        $location = $this->route('location');

        return $location instanceof Location
            ? ($this->user()?->can('update', $location) ?? false)
            : ($this->user()?->can('create', Location::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'latitude' => $this->input('latitude') === '' ? null : $this->input('latitude'),
            'longitude' => $this->input('longitude') === '' ? null : $this->input('longitude'),
            'geofence_radius_m' => $this->input('geofence_radius_m') === '' ? null : $this->input('geofence_radius_m'),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $ignore = $this->route('location')?->getKey();

        return [
            'name' => ['required', 'string', 'min:2', 'max:160', Rule::unique('locations', 'name')->where('organization_id', $tenantId)->ignore($ignore)],
            'type' => ['required', new Enum(LocationType::class)],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
            'region' => ['nullable', 'string', 'max:120'],
            // Geofence: all three or none. Ranges per requirements 04.
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude,geofence_radius_m'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude,geofence_radius_m'],
            'geofence_radius_m' => ['nullable', 'integer', 'between:10,5000', 'required_with:latitude,longitude'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.required_with' => __('Enter latitude, longitude and radius together to define a geofence.'),
            'longitude.required_with' => __('Enter latitude, longitude and radius together to define a geofence.'),
            'geofence_radius_m.required_with' => __('Enter latitude, longitude and radius together to define a geofence.'),
        ];
    }
}
