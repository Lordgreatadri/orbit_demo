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
    'leave_policy_id',
    'starts_on',
    'ends_on',
    'day_count',
    'covered_dates',
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
            'day_count' => 'integer',
            'covered_dates' => 'array',
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

    public function policy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id');
    }

    public function dayCount(): int
    {
        if ((int) $this->day_count > 0) {
            return (int) $this->day_count;
        }

        return (int) abs($this->starts_on->diffInDays($this->ends_on)) + 1;
    }

    /**
     * @return list<string>
     */
    public function coveredDates(): array
    {
        $stored = $this->covered_dates;

        if (is_array($stored) && $stored !== []) {
            return array_values($stored);
        }

        $dates = [];
        $cursor = $this->starts_on->copy();
        while ($cursor->lte($this->ends_on) && count($dates) < 366) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }
}
