<?php

namespace App\Http\Requests\Workforce;

use App\Workforce\WorkforceEvents;

class UpdateNotificationPreferencesRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('notifications.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'events' => ['required', 'array'],
            'events.*' => ['array'],
            'events.*.mail' => ['nullable', 'boolean'],
            'events.*.database' => ['nullable', 'boolean'],
            'events.*.sms' => ['nullable', 'boolean'],
            'events.*.push' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $unknown = array_diff(array_keys((array) $this->input('events', [])), WorkforceEvents::keys());

            if ($unknown !== []) {
                $validator->errors()->add('events', 'One of the notification events is not recognised.');
            }
        });
    }
}
