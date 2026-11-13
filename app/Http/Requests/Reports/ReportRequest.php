<?php

namespace App\Http\Requests\Reports;

use App\Http\Requests\Workforce\WorkforceRequest;
use App\Reports\ReportFormat;
use App\Reports\ReportRegistry;
use Illuminate\Validation\Rule;

class ReportRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        $key = (string) $this->route('report');

        try {
            $provider = app(ReportRegistry::class)->get($key);
        } catch (\InvalidArgumentException) {
            return false;
        }

        return $provider->authorize($this->user());
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'department' => $this->input('department') ?: null,
            'location' => $this->input('location') ?: null,
            'person' => $this->input('person') ?: null,
            'status' => $this->input('status') ?: null,
            'leave_type' => $this->input('leave_type') ?: null,
            'leave_status' => $this->input('leave_status') ?: null,
            'method' => $this->input('method') ?: null,
            'arrangement' => $this->input('arrangement') ?: null,
            'shift' => $this->input('shift') ?: null,
            'format' => $this->input('format') ?: null,
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
            'person' => ['nullable', 'uuid'],
            'status' => ['nullable', 'string'],
            'leave_type' => ['nullable', 'uuid'],
            'leave_status' => ['nullable', 'string'],
            'method' => ['nullable', 'string'],
            'arrangement' => ['nullable', 'string'],
            'shift' => ['nullable', 'uuid'],
            'format' => ['nullable', Rule::enum(ReportFormat::class)],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
