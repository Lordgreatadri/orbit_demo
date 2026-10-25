<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Enum;

class UpdateOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorisation is performed in the controller via the policy, with the bound model.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'contact_email' => Str::lower(trim((string) $this->input('contact_email'))),
            'contact_phone' => preg_replace('/[\s().-]+/', '', (string) $this->input('contact_phone')) ?: null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:160'],
            'type' => ['required', new Enum(OrganizationType::class)],
            'timezone' => ['required', 'string', 'timezone:all'],
            'contact_email' => ['required', 'string', 'email:rfc', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
            'address_line' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'contact_phone.regex' => __('Enter a phone number in international format, for example +233201234567.'),
        ];
    }
}
