<?php

namespace App\Http\Requests\Workforce;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

abstract class WorkforceRequest extends FormRequest
{
    protected function tenantId(): int
    {
        return (int) $this->user()?->organization_id;
    }

    protected function tenantExists(string $table, string $column = 'uuid'): Exists
    {
        return Rule::exists($table, $column)->where('organization_id', $this->tenantId());
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function coordinateRules(bool $required): array
    {
        $presence = $required ? 'required' : 'nullable';

        return [
            'latitude' => [$presence, 'numeric', 'between:-90,90'],
            'longitude' => [$presence, 'numeric', 'between:-180,180'],
            'accuracy_m' => [$presence, 'numeric', 'between:0,10000'],
            'captured_at' => [$presence, 'date'],
        ];
    }
}
