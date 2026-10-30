<?php

namespace App\Http\Requests\Organizations;

use App\Enums\OrganizationStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Policy check with the bound organisation happens in the controller.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Platform operators may activate, suspend, or close. "Pending verification"
            // is only ever set by the registration flow.
            'status' => ['required', Rule::in([
                OrganizationStatus::Active->value,
                OrganizationStatus::Suspended->value,
                OrganizationStatus::Closed->value,
            ])],
            'reason' => ['required', 'string', 'min:5', 'max:300'],
        ];
    }

    public function status(): OrganizationStatus
    {
        return OrganizationStatus::from($this->validated('status'));
    }
}
