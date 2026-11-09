<?php

namespace App\Http\Requests\Workforce;

use App\Models\FieldActivity;
use App\Models\FieldAssignment;

class RecordFieldLocationRequest extends WorkforceRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if ($user === null || ! $user->can('attendance.record')) {
            return false;
        }

        if ($user->can('attendance.manage')) {
            return true;
        }

        $assignment = $this->route('assignment');
        $activity = $this->route('activity');
        $staffId = $assignment instanceof FieldAssignment
            ? $assignment->staff_member_id
            : ($activity instanceof FieldActivity ? $activity->staff_member_id : null);

        return (int) $user->staffMember?->getKey() === (int) $staffId;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return $this->coordinateRules(true);
    }
}
