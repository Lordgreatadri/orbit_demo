<?php

namespace App\Http\Requests\Workforce;

class StoreShiftRequest extends WorkforceRequest
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
            'starts_at' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'ends_at' => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'effective_from' => ['required', 'date'],
            'effective_until' => ['nullable', 'date', 'after_or_equal:effective_from'],
            'department_uuid' => ['nullable', 'uuid', $this->tenantExists('departments')],
            'location_uuid' => ['nullable', 'uuid', $this->tenantExists('locations')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'starts_at.regex' => 'Enter a start time like 08:00.',
            'ends_at.regex' => 'Enter an end time like 17:00.',
        ];
    }

    public function startsAt(): string
    {
        return $this->clock($this->validated('starts_at'));
    }

    public function endsAt(): string
    {
        return $this->clock($this->validated('ends_at'));
    }

    private function clock(string $value): string
    {
        return strlen($value) === 5 ? $value.':00' : $value;
    }
}
