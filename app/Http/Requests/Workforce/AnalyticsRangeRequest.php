<?php

namespace App\Http\Requests\Workforce;

use App\Analytics\AnalyticsScope;
use Illuminate\Support\Carbon;

class AnalyticsRangeRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('analytics.view') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'department' => $this->input('department') ?: null,
            'location' => $this->input('location') ?: null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'department' => ['nullable', 'uuid'],
            'location' => ['nullable', 'uuid'],
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(): array
    {
        return AnalyticsScope::window(
            $this->user()->organization,
            $this->validated('from'),
            $this->validated('to'),
        );
    }
}
