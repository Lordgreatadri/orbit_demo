<?php

namespace App\Http\Requests\Workforce;

use Illuminate\Validation\Rule;

class StoreStaffCategoryRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('leave.policy') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => strtoupper(trim((string) $this->input('code'))),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'code' => ['required', 'string', 'max:32', 'alpha_dash', Rule::unique('staff_categories', 'code')->where('organization_id', $this->tenantId())],
        ];
    }
}
