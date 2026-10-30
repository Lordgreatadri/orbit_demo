<?php

namespace App\Models;

use App\Enums\StaffStatus;
use App\Enums\WorkType;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\StaffMemberFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A person in the organisation's register. Independent of whether they hold a
 * login account (`user_id` is optional and unique).
 */
#[Fillable([
    'organization_id',
    'user_id',
    'department_id',
    'primary_location_id',
    'staff_number',
    'first_name',
    'last_name',
    'email',
    'phone',
    'job_title',
    'work_type',
    'status',
    'started_on',
    'ended_on',
    'notes',
])]
class StaffMember extends Model
{
    /** @use HasFactory<StaffMemberFactory> */
    use BelongsToOrganization, HasFactory, HasPublicUuid, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_type' => WorkType::class,
            'status' => StaffStatus::class,
            'started_on' => 'date',
            'ended_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function primaryLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'primary_location_id');
    }

    protected function name(): Attribute
    {
        return Attribute::get(fn (): string => trim($this->first_name.' '.$this->last_name));
    }

    /**
     * Case-insensitive search across number, name, e-mail and job title.
     */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        $like = '%'.mb_strtolower($term).'%';

        return $query->where(function (Builder $q) use ($like): void {
            $q->whereRaw('LOWER(staff_number) LIKE ?', [$like])
                ->orWhereRaw('LOWER(first_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(last_name) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', [$like])
                ->orWhereRaw('LOWER(COALESCE(job_title, \'\')) LIKE ?', [$like]);
        });
    }
}
