<?php

namespace App\Http\Requests\Platform;

use Illuminate\Foundation\Http\FormRequest;

class SendSmsTestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'to' => ['required', 'string', 'regex:/^\+[1-9]\d{7,14}$/'],
            'body' => ['required', 'string', 'min:1', 'max:640'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'to.regex' => 'Enter the test number in international format, starting with +.',
        ];
    }
}
