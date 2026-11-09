<?php

namespace App\Http\Requests\Workforce;

use Illuminate\Validation\Rule;

class UpdateLeaveTypeRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('leave.approve') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $days = ['is_active' => $this->boolean('is_active')];

        if ($this->exists('default_days')) {
            $days['default_days'] = $this->filled('default_days') ? $this->input('default_days') : 0;
        }

        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'paid' => $this->boolean('paid'),
            'requires_balance' => $this->boolean('requires_balance'),
            ...$days,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $type = $this->route('leaveType');

        return [
            'name' => ['required', 'string', 'max:80'],
            'code' => [
                'required', 'string', 'max:32', 'alpha_dash',
                Rule::unique('leave_types', 'code')
                    ->where('organization_id', $this->tenantId())
                    ->ignore($type?->getKey()),
            ],
            'paid' => ['boolean'],
            'requires_balance' => ['boolean'],
            'default_days' => ['sometimes', 'integer', 'min:0', 'max:365'],
            'is_active' => ['boolean'],
        ];
    }
}
