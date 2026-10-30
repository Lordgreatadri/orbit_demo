<?php

namespace App\Http\Requests\Staff;

use App\Enums\StaffStatus;
use App\Enums\WorkType;
use App\Models\Department;
use App\Models\Location;
use App\Models\StaffMember;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreStaffMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StaffMember::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'staff_number' => Str::upper(trim((string) $this->input('staff_number'))),
            'email' => Str::lower(trim((string) $this->input('email'))) ?: null,
            'phone' => preg_replace('/[\s().-]+/', '', (string) $this->input('phone')) ?: null,
            'department' => $this->input('department') ?: null,
            'primary_location' => $this->input('primary_location') ?: null,
            'user' => $this->input('user') ?: null,
        ]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $tenantId = app(TenantContext::class)->id();

        return [
            'staff_number' => [
                'required', 'string', 'max:64', 'regex:/^[A-Z0-9][A-Z0-9\-\/_.]*$/',
                Rule::unique('staff_members', 'staff_number')
                    ->where('organization_id', $tenantId)
                    ->whereNull('deleted_at')
                    ->ignore($this->route('staff')?->getKey()),
            ],
            'first_name' => ['required', 'string', 'min:1', 'max:100'],
            'last_name' => ['required', 'string', 'min:1', 'max:100'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'regex:/^\+?[0-9]{9,15}$/'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'work_type' => ['required', new Enum(WorkType::class)],
            'status' => ['required', new Enum(StaffStatus::class)],
            'started_on' => ['nullable', 'date'],
            'ended_on' => ['nullable', 'date', 'after_or_equal:started_on'],
            'notes' => ['nullable', 'string', 'max:2000'],
            // Relationships are addressed by UUID and must belong to the current organisation.
            'department' => ['nullable', 'string', Rule::exists('departments', 'uuid')->where('organization_id', $tenantId)],
            'primary_location' => ['nullable', 'string', Rule::exists('locations', 'uuid')->where('organization_id', $tenantId)],
            'user' => ['nullable', 'string', Rule::exists('users', 'uuid')->where('organization_id', $tenantId)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'staff_number.regex' => __('Use letters, numbers, dashes, slashes, dots or underscores, starting with a letter or number.'),
            'staff_number.unique' => __('That staff number is already used in your organisation.'),
            'phone.regex' => __('Enter a phone number in international format, for example +233201234567.'),
            'user.unique' => __('That user account is already linked to another staff record.'),
        ];
    }

    /**
     * Resolve UUID inputs to internal ids. Called after validation.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $data = $this->safe()->except(['department', 'primary_location', 'user']);

        $data['department_id'] = $this->validated('department')
            ? Department::query()->where('uuid', $this->validated('department'))->value('id')
            : null;
        $data['primary_location_id'] = $this->validated('primary_location')
            ? Location::query()->where('uuid', $this->validated('primary_location'))->value('id')
            : null;
        $data['user_id'] = $this->validated('user')
            ? User::query()->where('uuid', $this->validated('user'))->where('organization_id', app(TenantContext::class)->id())->value('id')
            : null;

        return $data;
    }

    /**
     * One user account may be linked to at most one staff record. The check is
     * keyed by user_id (resolved from the UUID), ignoring the record being edited.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            $uuid = $this->input('user');

            if (! $uuid) {
                return;
            }

            $userId = User::query()
                ->where('uuid', $uuid)
                ->where('organization_id', app(TenantContext::class)->id())
                ->value('id');
            $current = $this->route('staff');

            $taken = StaffMember::query()
                ->where('user_id', $userId)
                ->when($current, fn ($q) => $q->whereKeyNot($current->getKey()))
                ->exists();

            if ($taken) {
                $validator->errors()->add('user', __('That user account is already linked to another staff record.'));
            }
        });
    }
}
