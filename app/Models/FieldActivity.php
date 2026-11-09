<?php

namespace App\Models;

use App\Enums\FieldActivityStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'organization_id',
    'field_assignment_id',
    'staff_member_id',
    'attendance_record_id',
    'started_at',
    'ended_at',
    'status',
    'last_latitude',
    'last_longitude',
    'last_accuracy_m',
    'last_reported_at',
])]
class FieldActivity extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'last_reported_at' => 'datetime',
            'status' => FieldActivityStatus::class,
            'last_latitude' => 'decimal:7',
            'last_longitude' => 'decimal:7',
            'last_accuracy_m' => 'integer',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(FieldAssignment::class, 'field_assignment_id');
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function attendanceRecord(): BelongsTo
    {
        return $this->belongsTo(AttendanceRecord::class);
    }

    /**
     * Display state from the last explicit report. Nothing is collected in the background.
     */
    public function displayStatus(int $delayMinutes): FieldActivityStatus
    {
        if ($this->status === FieldActivityStatus::Completed) {
            return FieldActivityStatus::Completed;
        }

        if ($this->last_reported_at === null) {
            return FieldActivityStatus::Offline;
        }

        if ($this->last_reported_at->lt(now()->subMinutes($delayMinutes))) {
            return FieldActivityStatus::Delayed;
        }

        return FieldActivityStatus::Active;
    }
}
