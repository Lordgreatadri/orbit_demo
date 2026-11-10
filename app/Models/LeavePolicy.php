<?php

namespace App\Models;

use App\Enums\LeaveApprovalMode;
use App\Enums\LeaveCountMode;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'leave_type_id',
    'name',
    'year_start_month',
    'year_start_day',
    'count_mode',
    'weekend_days',
    'exclude_non_working',
    'approval_mode',
    'allow_self_approval',
    'effective_from',
    'effective_until',
    'is_active',
])]
class LeavePolicy extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year_start_month' => 'integer',
            'year_start_day' => 'integer',
            'count_mode' => LeaveCountMode::class,
            'weekend_days' => 'array',
            'exclude_non_working' => 'boolean',
            'approval_mode' => LeaveApprovalMode::class,
            'allow_self_approval' => 'boolean',
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(LeaveEntitlementRule::class);
    }
}
