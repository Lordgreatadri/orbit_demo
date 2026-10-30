<?php

namespace App\Http\Requests\Access;

use App\Enums\RoleScope;
use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $role = $this->route('role');

        return $role instanceof Role
            ? ($this->user()?->can('update', $role) ?? false)
            : ($this->user()?->can('create', Role::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $role = $this->route('role');

        $this->merge([
            // System roles keep their machine name. Scope is frozen after create so a
            // tenant role cannot be flipped onto a platform account (or the reverse).
            'name' => $role instanceof Role && $role->is_system
                ? $role->name
                : Str::of((string) $this->input('name'))->lower()->trim()->replaceMatches('/[^a-z0-9]+/', '_')->trim('_')->toString(),
            'scope' => $role instanceof Role ? $role->scope->value : $this->input('scope'),
            'permissions' => array_values(array_filter((array) $this->input('permissions', []))),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $guard = config('auth.defaults.guard', 'web');

        return [
            'name' => [
                'required', 'string', 'min:3', 'max:64', 'regex:/^[a-z][a-z0-9_]*$/',
                Rule::unique('roles', 'name')->where('guard_name', $guard)->ignore($this->route('role')?->getKey()),
            ],
            'scope' => ['required', new Enum(RoleScope::class)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['present', 'array', 'max:200'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => __('Use lowercase letters, numbers and underscores, starting with a letter (e.g. field_supervisor).'),
        ];
    }
}
