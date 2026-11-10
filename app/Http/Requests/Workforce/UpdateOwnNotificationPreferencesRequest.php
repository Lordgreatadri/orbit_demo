<?php

namespace App\Http\Requests\Workforce;

use App\Workforce\WorkforceEvents;

class UpdateOwnNotificationPreferencesRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'categories' => ['required', 'array'],
            'categories.*' => ['array'],
            'categories.*.mail' => ['nullable', 'boolean'],
            'categories.*.database' => ['nullable', 'boolean'],
            'categories.*.sms' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $unknown = array_diff(array_keys((array) $this->input('categories', [])), array_keys(WorkforceEvents::categories()));

            if ($unknown !== []) {
                $validator->errors()->add('categories', 'One of the notification categories is not recognised.');
            }
        });
    }
}
