<?php

namespace App\Http\Requests\Access;

use App\Models\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PermissionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $permission = $this->route('permission');

        return $permission instanceof Permission
            ? ($this->user()?->can('update', $permission) ?? false)
            : ($this->user()?->can('create', Permission::class) ?? false);
    }

    protected function prepareForValidation(): void
    {
        $permission = $this->route('permission');

        $this->merge([
            'name' => $permission instanceof Permission && $permission->is_system
                ? $permission->name
                : Str::of((string) $this->input('name'))->lower()->trim()->replaceMatches('/[^a-z0-9.]+/', '_')->trim('_.')->toString(),
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
                'required', 'string', 'min:3', 'max:96', 'regex:/^[a-z][a-z0-9_]*(\.[a-z0-9_]+)+$/',
                Rule::unique('permissions', 'name')->where('guard_name', $guard)->ignore($this->route('permission')?->getKey()),
            ],
            'description' => ['required', 'string', 'min:3', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => __('Use the form module.action, e.g. reports.export. The module becomes the first segment.'),
        ];
    }
}
