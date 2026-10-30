<?php

namespace App\Http\Requests\Access;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Authorisation is performed in the controller (policy `manageAccess` with the
 * bound target); the action re-validates every role against the grant resolver.
 */
class SyncRolesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['roles' => array_values(array_filter((array) $this->input('roles', [])))]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'roles' => ['present', 'array', 'max:20'],
            'roles.*' => ['string', 'max:125', 'exists:roles,name'],
        ];
    }
}
