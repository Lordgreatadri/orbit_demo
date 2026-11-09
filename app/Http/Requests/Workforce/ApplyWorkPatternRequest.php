<?php

namespace App\Http\Requests\Workforce;

class ApplyWorkPatternRequest extends WorkforceRequest
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
            'from' => ['required', 'date'],
            'until' => ['required', 'date', 'after_or_equal:from'],
            'department_uuid' => ['nullable', 'uuid', $this->tenantExists('departments')],
        ];
    }
}
