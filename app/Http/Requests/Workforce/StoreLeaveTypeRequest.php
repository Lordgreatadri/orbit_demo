<?php

namespace App\Http\Requests\Workforce;

use Illuminate\Validation\Rule;

class StoreLeaveTypeRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('leave.policy') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
            'paid' => $this->boolean('paid'),
            'requires_balance' => $this->boolean('requires_balance'),
            'default_days' => $this->filled('default_days') ? $this->input('default_days') : 0,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'code' => [
                'required', 'string', 'max:32', 'alpha_dash',
                Rule::unique('leave_types', 'code')->where('organization_id', $this->tenantId()),
            ],
            'paid' => ['boolean'],
            'requires_balance' => ['boolean'],
            'default_days' => ['required', 'integer', 'min:0', 'max:365'],
        ];
    }
}
