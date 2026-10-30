<?php

namespace App\Http\Requests\Access;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StorePlatformUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('createPlatformAccount', User::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
            'phone' => preg_replace('/[\s().-]+/', '', (string) $this->input('phone')) ?: null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'min:1', 'max:100'],
            'last_name' => ['required', 'string', 'min:1', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
            'role' => [
                'required',
                'string',
                Rule::exists('roles', 'name')->where('scope', 'platform'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === 'platform_super_admin' && ! $this->user()?->isPlatformSuperAdmin()) {
                        $fail(__('Only a platform super administrator can create another super administrator.'));
                    }
                },
            ],
        ];
    }

    public function role(): Role
    {
        return Role::query()->where('name', $this->validated('role'))->firstOrFail();
    }
}
