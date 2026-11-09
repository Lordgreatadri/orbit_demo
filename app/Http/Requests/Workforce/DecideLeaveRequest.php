<?php

namespace App\Http\Requests\Workforce;

class DecideLeaveRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('leave.approve') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:approve,reject'],
            'decision_note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            if ($this->input('decision') === 'reject' && blank($this->input('decision_note'))) {
                $validator->errors()->add('decision_note', 'A rejection needs a reason.');
            }
        });
    }

    public function approved(): bool
    {
        return $this->validated('decision') === 'approve';
    }
}
