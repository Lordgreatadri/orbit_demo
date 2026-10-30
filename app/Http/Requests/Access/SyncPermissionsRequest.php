<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;

class SyncPermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['permissions' => array_values(array_filter((array) $this->input('permissions', [])))]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array', 'max:200'],
            'permissions.*' => ['string', 'max:125', 'exists:permissions,name'],
        ];
    }
}
