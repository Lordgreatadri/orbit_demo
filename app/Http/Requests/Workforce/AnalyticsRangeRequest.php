<?php

namespace App\Http\Requests\Workforce;

use Illuminate\Support\Carbon;

class AnalyticsRangeRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('analytics.view') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function range(): array
    {
        $timezone = $this->user()->organization->timezone ?: 'UTC';
        $from = Carbon::parse($this->validated('from') ?? now($timezone)->startOfMonth()->toDateString(), $timezone)->startOfDay();
        $to = Carbon::parse($this->validated('to') ?? now($timezone)->toDateString(), $timezone)->startOfDay();

        if ($from->gt($to) || $from->diffInDays($to) > 61) {
            $from = $to->copy()->subDays(30);
        }

        return [$from, $to];
    }
}
