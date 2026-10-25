<?php

namespace App\Http\Requests\Auth;

use App\Enums\OrganizationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\Password;

class RegisterOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => Str::lower(trim((string) $this->input('email'))),
            'phone' => preg_replace('/[\s().-]+/', '', (string) $this->input('phone')),
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'organization_name' => ['required', 'string', 'min:2', 'max:160'],
            'organization_type' => ['required', new Enum(OrganizationType::class)],
            'timezone' => ['required', 'string', 'timezone:all'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'string', 'email:rfc', 'max:255', Rule::unique('users', 'email')],
            'phone' => ['required', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
            'password' => ['required', 'string', 'confirmed', Password::defaults()],
            'terms' => ['accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'phone.regex' => __('Enter a phone number in international format, for example +233201234567.'),
            'terms.accepted' => __('You must accept the Terms and Conditions and Privacy Policy to register.'),
            'email.unique' => __('An account with this e-mail address already exists. Sign in or reset your password.'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'organization_name' => 'organisation name',
            'organization_type' => 'organisation type',
            'timezone' => 'time zone',
            'first_name' => 'first name',
            'last_name' => 'last name',
            'email' => 'e-mail',
        ];
    }
}
