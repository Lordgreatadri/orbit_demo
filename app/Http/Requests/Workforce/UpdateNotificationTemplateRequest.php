<?php

namespace App\Http\Requests\Workforce;

use App\Workforce\WorkforceEvents;
use Illuminate\Validation\Rule;

class UpdateNotificationTemplateRequest extends WorkforceRequest
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
            'event' => ['required', 'string', Rule::in(WorkforceEvents::keys())],
            'subject' => ['nullable', 'string', 'max:140'],
            'body' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $text = (string) $this->input('subject').(string) $this->input('body');

            if (preg_match('/<\?|\{!!|@php/i', $text) === 1) {
                $validator->errors()->add('body', 'Templates can only contain text and {{placeholders}}.');
            }
        });
    }
}
