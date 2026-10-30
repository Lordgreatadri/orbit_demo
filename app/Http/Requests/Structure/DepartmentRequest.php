<?php

namespace App\Http\Requests\Structure;

use App\Models\Department;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $department = $this->route('department');

        return $department instanceof Department
            ? ($this->user()?->can('update', $department) ?? false)
            : ($this->user()?->can('create', Department::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => trim((string) $this->input('name')),
            'code' => Str::upper(trim((string) $this->input('code'))) ?: null,
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();
        $ignore = $this->route('department')?->getKey();

        return [
            'name' => ['required', 'string', 'min:2', 'max:120', Rule::unique('departments', 'name')->where('organization_id', $tenantId)->ignore($ignore)],
            'code' => ['nullable', 'string', 'max:32', 'regex:/^[A-Z0-9\-_]+$/', Rule::unique('departments', 'code')->where('organization_id', $tenantId)->ignore($ignore)],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ];
    }
}
