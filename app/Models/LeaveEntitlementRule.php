<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'leave_policy_id',
    'priority',
    'staff_category_id',
    'department_id',
    'grade',
    'employment_type',
    'min_service_months',
    'max_service_months',
    'entitled_days',
    'effective_from',
    'effective_until',
])]
class LeaveEntitlementRule extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'min_service_months' => 'integer',
            'max_service_months' => 'integer',
            'entitled_days' => 'integer',
            'effective_from' => 'date',
            'effective_until' => 'date',
        ];
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(StaffCategory::class, 'staff_category_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function isCatchAll(): bool
    {
        return $this->staff_category_id === null
            && $this->department_id === null
            && blank($this->grade)
            && blank($this->employment_type)
            && $this->min_service_months === null
            && $this->max_service_months === null;
    }
}
