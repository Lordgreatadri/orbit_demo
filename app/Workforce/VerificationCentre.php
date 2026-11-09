<?php

namespace App\Workforce;

use App\Enums\LocationType;
use App\Enums\SessionStatus;
use App\Enums\VerificationMethod;
use App\Enums\WorkArrangementKind;
use App\Enums\WorkType;
use App\Models\AttendanceOtp;
use App\Models\AttendanceSession;
use App\Models\AttendanceToken;
use App\Models\Beacon;
use App\Models\BoundDevice;
use App\Models\Location;
use App\Models\StaffMember;
use App\Models\User;
use App\Models\WifiNetwork;
use App\Models\WorkforcePolicy;
use Illuminate\Support\Carbon;

/**
 * Runs each requested verification method and returns decisions.
 * It does not write attendance. The engine decides and records the outcome.
 */
final class VerificationCentre
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function assess(User $actor, StaffMember $staff, WorkforcePolicy $policy, array $payload, ?string $scheduledKind = null): VerificationBundle
    {
        $methods = $this->methods($payload);
        $decisions = [];
        $session = null;
        $location = $this->explicitLocation($staff, $payload);

        foreach ($methods as $method) {
            if ($method->isReserved() || ! $policy->allows($method)) {
                $decisions[] = new VerificationDecision(
                    $method,
                    false,
                    $method->isReserved() ? 'method_not_enabled' : 'method_disabled',
                );

                continue;
            }

            $decision = match ($method) {
                VerificationMethod::Qr => $this->qr($staff, $payload, $session, $location),
                VerificationMethod::Gps => $this->gps($staff, $policy, $payload, $location, $scheduledKind),
                VerificationMethod::Ble => $this->ble($staff, $payload, $location),
                VerificationMethod::Wifi => $this->wifi($staff, $payload, $location),
                VerificationMethod::Otp => $this->otp($staff, $policy, $payload),
                VerificationMethod::Device => $this->device($actor, $policy, $payload),
                VerificationMethod::Manual => $this->manual($actor, $staff, $policy, $scheduledKind),
                default => new VerificationDecision($method, false, 'method_not_enabled'),
            };

            $decisions[] = $decision;
        }

        if ($decisions === []) {
            $decisions[] = new VerificationDecision(VerificationMethod::Manual, false, 'verification_failed');
        }

        return new VerificationBundle($decisions, $session, $location);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<VerificationMethod>
     */
    private function methods(array $payload): array
    {
        $values = array_values(array_unique(array_filter((array) ($payload['methods'] ?? []))));
        $methods = [];

        foreach ($values as $value) {
            $method = VerificationMethod::tryFrom((string) $value);
            if ($method !== null) {
                $methods[] = $method;
            }
        }

        return $methods;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function explicitLocation(StaffMember $staff, array $payload): ?Location
    {
        $uuid = (string) ($payload['location_uuid'] ?? '');

        if ($uuid !== '') {
            return Location::query()->where('uuid', $uuid)->where('is_active', true)->first();
        }

        return $staff->primaryLocation;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function qr(StaffMember $staff, array $payload, ?AttendanceSession &$session, ?Location &$location): VerificationDecision
    {
        $raw = $this->qrToken((string) ($payload['token'] ?? ''));

        if ($raw === '') {
            return new VerificationDecision(VerificationMethod::Qr, false, 'invalid_token');
        }

        $token = AttendanceToken::query()->where('token_hash', hash('sha256', $raw))->first();

        if ($token === null || (int) $token->organization_id !== (int) $staff->organization_id) {
            return new VerificationDecision(VerificationMethod::Qr, false, 'invalid_token');
        }

        if ($token->expires_at->lte(now())) {
            return new VerificationDecision(VerificationMethod::Qr, false, 'token_expired');
        }

        $found = AttendanceSession::query()->with('location')->find($token->attendance_session_id);

        if ($found === null || $found->status !== SessionStatus::Active || ! $found->isOpen()) {
            return new VerificationDecision(VerificationMethod::Qr, false, 'session_closed');
        }

        $session = $found;

        if ($found->location_id !== null) {
            $sessionLocation = $found->location;
            if ($location !== null && (int) $location->getKey() !== (int) $found->location_id) {
                return new VerificationDecision(VerificationMethod::Qr, false, 'location_conflict');
            }
            $location = $sessionLocation;
        }

        return new VerificationDecision(
            VerificationMethod::Qr,
            true,
            'qr_accepted',
            [
                'session' => $found->uuid,
                'expires_at' => $token->expires_at->toIso8601String(),
            ],
        );
    }

    /**
     * Accept either the raw code or the check-in address encoded in the image.
     */
    private function qrToken(string $raw): string
    {
        $raw = trim($raw);
        $marker = '/attendance/arrive/';
        $position = strrpos($raw, $marker);

        if ($position === false) {
            return $raw;
        }

        $segment = rawurldecode(substr($raw, $position + strlen($marker)));

        return explode('?', $segment, 2)[0];
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function gps(StaffMember $staff, WorkforcePolicy $policy, array $payload, ?Location $location, ?string $scheduledKind = null): VerificationDecision
    {
        $lat = $payload['latitude'] ?? null;
        $lng = $payload['longitude'] ?? null;
        $accuracy = $payload['accuracy_m'] ?? null;
        $captured = $payload['captured_at'] ?? null;
        $evidence = $this->gpsEvidence($lat, $lng, $accuracy, $captured, $location, 'unavailable');

        if ($lat === null || $lat === '' || $lng === null || $lng === '') {
            return new VerificationDecision(VerificationMethod::Gps, false, 'location_unavailable', $evidence);
        }

        if ($captured === null || $captured === '' || abs(now()->diffInSeconds(Carbon::parse($captured))) > $policy->location_max_age_seconds) {
            $evidence['geofence_result'] = 'stale';

            return new VerificationDecision(VerificationMethod::Gps, false, 'stale_location', $evidence);
        }

        if ($accuracy === null || (float) $accuracy > $policy->max_gps_accuracy_m) {
            $evidence['geofence_result'] = 'inaccurate';

            return new VerificationDecision(VerificationMethod::Gps, false, 'inaccurate_location', $evidence);
        }

        if ($location === null || ! $location->hasGeofence()) {
            if ($this->remoteAllowed($staff, $policy, $location, $scheduledKind)) {
                $evidence['geofence_result'] = 'remote';

                return new VerificationDecision(VerificationMethod::Gps, true, 'remote_allowed', $evidence);
            }

            $evidence['geofence_result'] = 'not_configured';

            return new VerificationDecision(VerificationMethod::Gps, false, 'location_not_configured', $evidence);
        }

        $distance = Geofence::distanceMetres((float) $lat, (float) $lng, (float) $location->latitude, (float) $location->longitude);
        $inside = $distance <= $location->geofence_radius_m;
        $evidence['geofence_result'] = $inside ? 'inside' : 'outside';
        $evidence['distance_m'] = (int) round($distance);
        $evidence['radius_m'] = (int) $location->geofence_radius_m;

        return new VerificationDecision(
            VerificationMethod::Gps,
            $inside,
            $inside ? 'inside_geofence' : 'outside_geofence',
            $evidence,
        );
    }

    /**
     * Registered beacon identifier supplied by the signed-in person.
     * This does not scan for a Bluetooth advertisement and does not prove proximity.
     *
     * @param  array<string, mixed>  $payload
     */
    private function ble(StaffMember $staff, array $payload, ?Location &$location): VerificationDecision
    {
        $publicId = trim((string) ($payload['beacon_public_id'] ?? ''));
        $beacon = $publicId === '' ? null : Beacon::query()->where('public_id', $publicId)->where('is_active', true)->first();

        if ($beacon === null || (int) $beacon->organization_id !== (int) $staff->organization_id) {
            return new VerificationDecision(VerificationMethod::Ble, false, 'beacon_unknown');
        }

        if ($location !== null && (int) $location->getKey() !== (int) $beacon->location_id) {
            return new VerificationDecision(VerificationMethod::Ble, false, 'location_conflict');
        }

        $location ??= $beacon->location()->first();

        $evidence = [
            'beacon' => $beacon->name,
            'beacon_id' => $beacon->uuid,
            'location_id' => $beacon->location_id,
            'evidence_kind' => 'registered_identifier',
            'proximity_verified' => false,
        ];

        if (isset($payload['rssi'])) {
            $evidence['reported_rssi'] = (int) $payload['rssi'];
        }

        return new VerificationDecision(VerificationMethod::Ble, true, 'beacon_registered', $evidence);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function wifi(StaffMember $staff, array $payload, ?Location &$location): VerificationDecision
    {
        $bssid = WifiNetwork::normalizeBssid((string) ($payload['bssid'] ?? ''));
        $network = $bssid === '' ? null : WifiNetwork::query()->where('bssid', $bssid)->where('is_active', true)->first();

        if ($network === null || (int) $network->organization_id !== (int) $staff->organization_id) {
            return new VerificationDecision(VerificationMethod::Wifi, false, 'wifi_unknown');
        }

        if ($location !== null && (int) $location->getKey() !== (int) $network->location_id) {
            return new VerificationDecision(VerificationMethod::Wifi, false, 'location_conflict');
        }

        $location ??= $network->location()->first();

        return new VerificationDecision(VerificationMethod::Wifi, true, 'wifi_matched', [
            'network' => $network->label,
        ]);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function otp(StaffMember $staff, WorkforcePolicy $policy, array $payload): VerificationDecision
    {
        if (! $policy->otp_enabled) {
            return new VerificationDecision(VerificationMethod::Otp, false, 'otp_disabled');
        }

        $code = trim((string) ($payload['otp'] ?? ''));
        if ($code === '') {
            return new VerificationDecision(VerificationMethod::Otp, false, 'otp_invalid');
        }

        $otp = AttendanceOtp::query()
            ->where('staff_member_id', $staff->getKey())
            ->where('code_hash', hash('sha256', $code))
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if ($otp === null) {
            return new VerificationDecision(VerificationMethod::Otp, false, 'otp_invalid');
        }

        return new VerificationDecision(VerificationMethod::Otp, true, 'otp_accepted', [], otpId: $otp->getKey());
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function device(User $actor, WorkforcePolicy $policy, array $payload): VerificationDecision
    {
        $presented = strtolower(trim((string) ($payload['device_hash'] ?? '')));

        if (! preg_match('/^[a-f0-9]{32,64}$/', $presented)) {
            return new VerificationDecision(VerificationMethod::Device, false, $policy->require_device_binding ? 'device_missing' : 'device_skipped');
        }

        $hash = hash('sha256', $presented);
        $existing = BoundDevice::query()->where('user_id', $actor->getKey())->get();

        if ($existing->isEmpty()) {
            return new VerificationDecision(VerificationMethod::Device, true, 'device_enrolled', ['device_hash' => $hash]);
        }

        $matched = $existing->contains(fn (BoundDevice $device) => hash_equals($device->device_hash, $hash));

        return new VerificationDecision(
            VerificationMethod::Device,
            $matched,
            $matched ? 'device_matched' : 'device_mismatch',
        );
    }

    private function manual(User $actor, StaffMember $staff, WorkforcePolicy $policy, ?string $scheduledKind = null): VerificationDecision
    {
        if ($this->scheduledOnSite($scheduledKind)) {
            return new VerificationDecision(VerificationMethod::Manual, false, 'manual_not_allowed');
        }

        if ($this->scheduledAway($scheduledKind) && $policy->remote_without_geofence) {
            return new VerificationDecision(VerificationMethod::Manual, true, 'manual_accepted');
        }

        $remote = in_array($staff->work_type, [WorkType::Remote, WorkType::Hybrid], true);

        if ($remote && $policy->remote_without_geofence) {
            return new VerificationDecision(VerificationMethod::Manual, true, 'manual_accepted');
        }

        return new VerificationDecision(VerificationMethod::Manual, false, 'manual_not_allowed');
    }

    private function remoteAllowed(StaffMember $staff, WorkforcePolicy $policy, ?Location $location, ?string $scheduledKind = null): bool
    {
        if (! $policy->remote_without_geofence) {
            return false;
        }

        if ($this->scheduledOnSite($scheduledKind)) {
            return false;
        }

        $away = $this->scheduledAway($scheduledKind)
            || ($scheduledKind === null && in_array($staff->work_type, [WorkType::Remote, WorkType::Hybrid], true));

        if (! $away) {
            return false;
        }

        return $location === null || $location->type === LocationType::Remote || ! $location->hasGeofence();
    }

    private function scheduledOnSite(?string $kind): bool
    {
        return in_array($kind, [WorkArrangementKind::Office->value, WorkArrangementKind::Field->value], true);
    }

    private function scheduledAway(?string $kind): bool
    {
        return in_array($kind, [WorkArrangementKind::Remote->value, WorkArrangementKind::Flexible->value], true);
    }

    /**
     * @return array<string, mixed>
     */
    private function gpsEvidence(mixed $lat, mixed $lng, mixed $accuracy, mixed $captured, ?Location $location, string $geofenceResult): array
    {
        $capturedAt = null;

        if ($captured !== null && $captured !== '') {
            $capturedAt = Carbon::parse($captured)->utc()->toIso8601String();
        }

        return [
            'latitude' => $lat === null || $lat === '' ? null : round((float) $lat, 5),
            'longitude' => $lng === null || $lng === '' ? null : round((float) $lng, 5),
            'accuracy_m' => $accuracy === null || $accuracy === '' ? null : (int) round((float) $accuracy),
            'captured_at' => $capturedAt,
            'location_id' => $location?->getKey(),
            'location' => $location?->name,
            'geofence_result' => $geofenceResult,
        ];
    }
}
