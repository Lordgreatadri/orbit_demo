<?php

namespace App\Http\Requests\Access;

use App\Enums\UserStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

class UpdateStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', new Enum(UserStatus::class)],
            'reason' => ['nullable', 'string', 'max:200'],
        ];
    }

    public function status(): UserStatus
    {
        return UserStatus::from($this->validated('status'));
    }
}
