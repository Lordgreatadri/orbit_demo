<?php

namespace App\Http\Requests\Workforce;

class StoreWorkPatternRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('schedules.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'starts_at' => ['required', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/'],
            'ends_at' => ['required', 'regex:/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/'],
            'weekdays' => ['required', 'array', 'min:1'],
            'weekdays.*' => ['integer', 'between:1,7'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'department_uuid' => ['nullable', 'uuid', $this->tenantExists('departments')],
            'location_uuid' => ['nullable', 'uuid', $this->tenantExists('locations')],
            'arrangement_uuid' => ['nullable', 'uuid', $this->tenantExists('work_arrangements')],
        ];
    }

    public function startsAt(): string
    {
        $value = (string) $this->validated('starts_at');

        return strlen($value) === 5 ? $value.':00' : $value;
    }

    public function endsAt(): string
    {
        $value = (string) $this->validated('ends_at');

        return strlen($value) === 5 ? $value.':00' : $value;
    }
}
