<?php

namespace App\Http\Requests\Tenancy;

use App\Enums\DomainType;
use App\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Organization $organization */
        $organization = $this->route('organization');

        return $this->user()?->can('manageWorkspace', $organization) ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'domain' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::enum(DomainType::class)],
            'make_primary' => ['sometimes', 'boolean'],
        ];
    }
}
