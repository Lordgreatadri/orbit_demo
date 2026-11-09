<?php

namespace App\Workforce;

use App\Enums\SessionStatus;
use App\Models\AttendanceSession;
use App\Models\AttendanceToken;
use App\Models\Location;
use App\Models\User;
use App\Models\WorkforcePolicy;
use App\Support\Audit;
use App\Tenancy\Origin;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

final class QrSessionService
{
    public function open(User $actor, ?Location $location, string $name, Carbon $starts, Carbon $ends, ?int $ttl, bool $requireLocation): AttendanceSession
    {
        $policy = WorkforcePolicy::resolve($actor->organization);
        $ttl = min(120, max(10, $ttl ?? $policy->qr_ttl_seconds));

        $session = AttendanceSession::query()->create([
            'organization_id' => $actor->organization_id,
            'location_id' => $location?->getKey(),
            'created_by' => $actor->getKey(),
            'name' => $name,
            'starts_at' => $starts->copy()->utc(),
            'ends_at' => $ends->copy()->utc(),
            'status' => SessionStatus::Active,
            'qr_ttl_seconds' => $ttl,
            'require_location' => $requireLocation,
        ]);

        Audit::log('attendance.session_opened', $session, ['name' => $session->name], $actor);
        app(WorkforceNotifier::class)->sessionOpened($session);

        return $session;
    }

    public function setStatus(User $actor, AttendanceSession $session, SessionStatus $status): void
    {
        $session->update(['status' => $status]);
        Audit::log('attendance.session_'.$status->value, $session, [], $actor);
    }

    /**
     * Issues a short-lived code and returns the raw value once. Only its hash is stored.
     * When $replace is the raw code this screen was showing, that code expires now.
     * Other screens keep their own timer. The code stays valid for every eligible
     * person until it expires. A second check-in by the same person is refused
     * on the attendance record, not by burning the code.
     */
    public function issue(AttendanceSession $session, ?string $replace = null): string
    {
        if (! $session->isOpen()) {
            throw new AttendanceException('session_closed', 'This attendance session is not active.');
        }

        $replace = trim((string) $replace);

        if ($replace !== '') {
            AttendanceToken::query()
                ->where('attendance_session_id', $session->getKey())
                ->where('token_hash', hash('sha256', $replace))
                ->where('expires_at', '>', now())
                ->update(['expires_at' => now()]);
        }

        $raw = Str::random(48);

        AttendanceToken::query()->create([
            'organization_id' => $session->organization_id,
            'attendance_session_id' => $session->getKey(),
            'token_hash' => hash('sha256', $raw),
            'expires_at' => now()->addSeconds($session->qr_ttl_seconds),
        ]);

        return $raw;
    }

    /**
     * Address encoded in the on-screen code. A phone camera opens a one-tap
     * check-in. The raw token is not mailed and expires with the rotation.
     */
    public function checkInUrl(AttendanceSession $session, string $rawToken): string
    {
        $session->loadMissing('organization');

        return Origin::url($session->organization, '/org/attendance/arrive/'.$rawToken);
    }

    public function image(AttendanceSession $session, string $rawToken): string
    {
        return QrSvg::render($this->checkInUrl($session, $rawToken));
    }

    /**
     * The session behind a still-valid code, without consuming it.
     */
    public function openSessionForToken(string $rawToken): ?AttendanceSession
    {
        $token = AttendanceToken::query()
            ->where('token_hash', hash('sha256', trim($rawToken)))
            ->first();

        if ($token === null || $token->expires_at->lte(now())) {
            return null;
        }

        $session = AttendanceSession::query()
            ->with('location:id,name')
            ->find($token->attendance_session_id);

        if ($session === null || ! $session->isOpen() || (int) $session->organization_id !== (int) $token->organization_id) {
            return null;
        }

        return $session;
    }
}
