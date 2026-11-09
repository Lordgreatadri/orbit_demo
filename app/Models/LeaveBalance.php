<?php

namespace App\Models;

use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'staff_member_id',
    'leave_type_id',
    'year',
    'period_start',
    'period_end',
    'entitled_days',
    'used_days',
    'pending_days',
    'adjusted_days',
])]
class LeaveBalance extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'entitled_days' => 'integer',
            'used_days' => 'integer',
            'pending_days' => 'integer',
            'adjusted_days' => 'integer',
        ];
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function available(): int
    {
        return (int) $this->entitled_days + (int) $this->adjusted_days - (int) $this->used_days - (int) $this->pending_days;
    }
}
