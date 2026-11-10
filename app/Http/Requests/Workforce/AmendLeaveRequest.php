<?php

namespace App\Http\Requests\Workforce;

class AmendLeaveRequest extends WorkforceRequest
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
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'reason' => ['required', 'string', 'max:1000'],
        ];
    }
}
