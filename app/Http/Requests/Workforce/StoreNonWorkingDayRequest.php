<?php

namespace App\Http\Requests\Workforce;

use App\Enums\NonWorkingKind;
use Illuminate\Validation\Rule;

class StoreNonWorkingDayRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('schedules.manage') ?? false;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'observed_on' => ['required', 'date'],
            'name' => ['required', 'string', 'max:120'],
            'kind' => ['required', Rule::enum(NonWorkingKind::class)],
        ];
    }
}
