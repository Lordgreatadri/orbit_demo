<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'organization_id',
    'staff_member_id',
    'user_id',
    'department_id',
    'location_id',
    'attendance_session_id',
    'shift_assignment_id',
    'work_date',
    'period_key',
    'check_in_at',
    'check_out_at',
    'status',
    'source',
    'late_minutes',
    'early_leave_minutes',
    'note',
])]
class AttendanceRecord extends Model
{
    use BelongsToOrganization, HasPublicUuid;

    /**
     * One row per person for a calendar day when no session or shift applies.
     * A later schedule can use its own key without colliding with a session.
     */
    public static function dayPeriod(string $workDate): string
    {
        return 'day:'.substr($workDate, 0, 10);
    }

    public static function sessionPeriod(int $sessionId): string
    {
        return 'session:'.$sessionId;
    }

    public static function shiftPeriod(int $assignmentId): string
    {
        return 'shift:'.$assignmentId;
    }

    protected static function booted(): void
    {
        static::creating(function (self $record): void {
            if (filled($record->period_key)) {
                return;
            }

            $date = $record->work_date;
            $day = $date instanceof \DateTimeInterface ? $date->format('Y-m-d') : substr((string) $date, 0, 10);

            $record->period_key = $record->attendance_session_id
                ? self::sessionPeriod((int) $record->attendance_session_id)
                : self::dayPeriod($day);
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'date',
            'check_in_at' => 'datetime',
            'check_out_at' => 'datetime',
            'status' => AttendanceStatus::class,
            'late_minutes' => 'integer',
            'early_leave_minutes' => 'integer',
        ];
    }

    public function staffMember(): BelongsTo
    {
        return $this->belongsTo(StaffMember::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(AttendanceSession::class, 'attendance_session_id');
    }

    public function shiftAssignment(): BelongsTo
    {
        return $this->belongsTo(ShiftAssignment::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(AttendanceEvent::class);
    }
}
