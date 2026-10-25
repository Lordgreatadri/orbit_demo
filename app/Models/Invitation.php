<?php

namespace App\Models;

use App\Enums\InvitationStatus;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\HasPublicUuid;
use Database\Factories\InvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'organization_id',
    'invited_by_id',
    'accepted_user_id',
    'email',
    'role',
    'token_hash',
    'expires_at',
    'accepted_at',
    'revoked_at',
])]
#[Hidden(['token_hash'])]
class Invitation extends Model
{
    /** @use HasFactory<InvitationFactory> */
    use BelongsToOrganization, HasFactory, HasPublicUuid;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by_id');
    }

    public function acceptedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_user_id');
    }

    protected function status(): Attribute
    {
        return Attribute::get(function (): InvitationStatus {
            if ($this->accepted_at !== null) {
                return InvitationStatus::Accepted;
            }

            if ($this->revoked_at !== null) {
                return InvitationStatus::Revoked;
            }

            if ($this->expires_at !== null && $this->expires_at->isPast()) {
                return InvitationStatus::Expired;
            }

            return InvitationStatus::Pending;
        });
    }

    public function isPending(): bool
    {
        return $this->status === InvitationStatus::Pending;
    }

    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereNull('accepted_at')
            ->whereNull('revoked_at')
            ->where('expires_at', '>', now());
    }

    /**
     * Generate a raw token and its storage hash. The raw token is only ever
     * placed in the invitation e-mail; the database keeps the hash.
     *
     * @return array{0: string, 1: string}
     */
    public static function generateToken(): array
    {
        $raw = Str::random(64);

        return [$raw, static::hashToken($raw)];
    }

    public static function hashToken(string $raw): string
    {
        return hash('sha256', $raw);
    }
}
