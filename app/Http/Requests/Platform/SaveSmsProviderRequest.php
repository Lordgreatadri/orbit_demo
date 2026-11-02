<?php

namespace App\Http\Requests\Platform;

use App\Integrations\Sms\SmsProviderRegistry;
use App\Models\SmsProvider;
use Illuminate\Foundation\Http\FormRequest;

class SaveSmsProviderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $thresholds = [];

        foreach (['low_balance_threshold', 'low_bundle_threshold'] as $field) {
            if ($this->input($field) === '') {
                $thresholds[$field] = null;
            }
        }

        $this->merge([
            'enabled' => $this->boolean('enabled'),
            'is_active' => $this->boolean('is_active'),
            'is_fallback' => $this->boolean('is_fallback'),
            'sandbox' => $this->boolean('sandbox'),
        ] + $thresholds);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var SmsProvider $provider */
        $provider = $this->route('smsProvider');
        $adapter = app(SmsProviderRegistry::class)->get($provider->key);

        $rules = [
            'sender_id' => ['nullable', 'string', 'max:34'],
            'enabled' => ['boolean'],
            'is_active' => ['boolean'],
            'is_fallback' => ['boolean'],
            'timeout_seconds' => ['required', 'integer', 'min:3', 'max:30'],
            'max_attempts' => ['required', 'integer', 'min:1', 'max:5'],
            'requests_per_minute' => ['required', 'integer', 'min:1', 'max:600'],
            'low_balance_threshold' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
            'low_bundle_threshold' => ['nullable', 'integer', 'min:0', 'max:99999999'],
            'backoff_seconds' => ['required', 'string', 'max:40', 'regex:/^\d{1,4}(\s*,\s*\d{1,4}){0,4}$/'],
        ];

        if ($adapter->supportsSandbox()) {
            $rules['sandbox'] = ['boolean'];
        }

        foreach ($adapter->credentialFields() as $field) {
            $rules['credentials.'.$field['key']] = ['nullable', 'string', 'max:500'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'sender_id' => 'sender ID',
            'backoff_seconds' => 'retry delays',
            'low_balance_threshold' => 'cash threshold',
            'low_bundle_threshold' => 'SMS bundle threshold',
        ];
    }
}
