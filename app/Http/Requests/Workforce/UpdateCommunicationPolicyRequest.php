<?php

namespace App\Http\Requests\Workforce;

class UpdateCommunicationPolicyRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('notifications.manage') ?? false;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'email_enabled' => ['nullable', 'boolean'],
            'sms_enabled' => ['nullable', 'boolean'],
            'in_app_enabled' => ['nullable', 'boolean'],
            'leave_notifications_enabled' => ['nullable', 'boolean'],
            'schedule_notifications_enabled' => ['nullable', 'boolean'],
            'attendance_reminders_enabled' => ['nullable', 'boolean'],
            'announcements_enabled' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function flags(): array
    {
        $flags = [];

        foreach (array_keys($this->rules()) as $key) {
            $flags[$key] = $this->boolean($key);
        }

        return $flags;
    }
}
