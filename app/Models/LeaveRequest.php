<?php

namespace App\Models;

use App\Enums\LeaveStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'staff_member_id',
    'leave_type_id',
    'starts_on',
    'ends_on',
    'reason',
    'status',
    'document_path',
    'decided_by',
    'decision_note',
    'decided_at',
])]
class LeaveRequest extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'status' => LeaveStatus::class,
            'decided_at' => 'datetime',
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

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function dayCount(): int
    {
        return (int) abs($this->starts_on->diffInDays($this->ends_on)) + 1;
    }
}
