<?php

namespace App\Http\Requests\Staff;

class UpdateStaffMemberRequest extends StoreStaffMemberRequest
{
    public function authorize(): bool
    {
        $staff = $this->route('staff');

        return $staff !== null && ($this->user()?->can('update', $staff) ?? false);
    }
}
