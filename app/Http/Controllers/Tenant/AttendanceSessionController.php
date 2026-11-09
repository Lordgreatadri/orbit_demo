<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workforce\IssueAttendanceOtpRequest;
use App\Http\Requests\Workforce\StoreAttendanceSessionRequest;
use App\Http\Requests\Workforce\StoreBeaconRequest;
use App\Http\Requests\Workforce\StoreWifiNetworkRequest;
use App\Models\AttendanceOtp;
use App\Models\AttendanceSession;
use App\Models\Beacon;
use App\Models\Location;
use App\Models\StaffMember;
use App\Models\WifiNetwork;
use App\Models\WorkforcePolicy;
use App\Workforce\AttendanceException;
use App\Workforce\QrSessionService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceSessionController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('attendance.manage'), 403);

        $status = SessionStatus::tryFrom((string) $request->query('status', ''));
        $locationUuid = (string) $request->query('location', '');
        $term = trim(str_replace(['%', '_'], '', (string) $request->query('q', '')));
        $locations = Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'uuid', 'name']);

        $sessions = AttendanceSession::query()
            ->with(['location:id,name', 'creator:id,first_name,last_name'])
            ->when($term !== '', fn ($query) => $query->where('name', 'like', '%'.$term.'%'))
            ->when($status, fn ($query) => $query->where('status', $status->value))
            ->when($locationUuid !== '', function ($query) use ($locations, $locationUuid): void {
                $query->where('location_id', $locations->firstWhere('uuid', $locationUuid)?->getKey() ?? 0);
            })
            ->latest('starts_at')
            ->paginate(25)
            ->withQueryString();

        $counts = AttendanceSession::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn (AttendanceSession $row) => [
                $row->status instanceof SessionStatus ? $row->status->value : (string) $row->status => (int) $row->total,
            ]);

        return view('tenant.attendance.sessions', [
            'sessions' => $sessions,
            'locations' => $locations,
            'beacons' => Beacon::query()->with('location:id,name')->orderBy('name')->paginate(8, ['*'], 'beacons_page')->withQueryString(),
            'networks' => WifiNetwork::query()->with('location:id,name')->orderBy('label')->paginate(8, ['*'], 'networks_page')->withQueryString(),
            'summary' => [
                'open' => AttendanceSession::query()
                    ->where('status', SessionStatus::Active->value)
                    ->where('starts_at', '<=', now())
                    ->where('ends_at', '>=', now())
                    ->count(),
                'active' => (int) ($counts[SessionStatus::Active->value] ?? 0),
                'paused' => (int) ($counts[SessionStatus::Paused->value] ?? 0),
                'closed' => (int) ($counts[SessionStatus::Closed->value] ?? 0),
                'total' => (int) $counts->sum(),
            ],
            'timezone' => $request->user()->organization->timezone ?: 'UTC',
        ]);
    }

    public function store(StoreAttendanceSessionRequest $request, QrSessionService $sessions): RedirectResponse
    {
        $input = $request->validated();

        $location = isset($input['location_uuid'])
            ? Location::query()->where('uuid', $input['location_uuid'])->firstOrFail()
            : null;

        $timezone = $request->user()->organization->timezone ?: 'UTC';
        $session = $sessions->open(
            $request->user(),
            $location,
            $input['name'],
            Carbon::parse($input['starts_at'], $timezone),
            Carbon::parse($input['ends_at'], $timezone),
            $input['qr_ttl_seconds'] ?? null,
            (bool) $input['require_location'],
        );

        $message = $session->starts_at->lte(now())
            ? 'Attendance session is active. People who can check in have been told where and when.'
            : 'Attendance session is scheduled. People who can check in have been told where and when.';

        return redirect()->route('org.attendance.sessions.show', $session)->with('status', $message);
    }

    public function show(Request $request, AttendanceSession $session, QrSessionService $sessions): View
    {
        abort_unless($request->user()->can('attendance.manage'), 403);
        $session->load('location:id,name');

        $token = null;
        $svg = null;

        if ($session->isOpen()) {
            try {
                $token = $sessions->issue($session);
                $svg = $sessions->image($session, $token);
            } catch (AttendanceException) {
                $token = null;
            }
        }

        $timezone = $request->user()->organization->timezone ?: 'UTC';

        return view('tenant.attendance.session', [
            'session' => $session,
            'token' => $token,
            'svg' => $svg,
            'timezone' => $timezone,
            'unavailable' => match (true) {
                $session->status === SessionStatus::Paused => 'This session is paused. Resume it to show a code.',
                $session->status === SessionStatus::Closed => 'This session is closed.',
                $session->ends_at->isPast() => 'The time window has ended, so no code is shown.',
                $session->starts_at->isFuture() => 'The code appears when the time window starts.',
                default => 'The code appears while the session is active and inside its time window.',
            },
        ]);
    }

    public function token(Request $request, AttendanceSession $session, QrSessionService $sessions): JsonResponse
    {
        abort_unless($request->user()->can('attendance.manage'), 403);

        $previous = trim((string) $request->input('previous', ''));

        if (strlen($previous) > 200) {
            $previous = '';
        }

        try {
            $token = $sessions->issue($session, $previous !== '' ? $previous : null);
        } catch (AttendanceException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'token' => $token,
            'svg' => $sessions->image($session, $token),
            'expires_in' => $session->qr_ttl_seconds,
        ]);
    }

    public function status(Request $request, AttendanceSession $session, QrSessionService $sessions, string $status): RedirectResponse
    {
        abort_unless($request->user()->can('attendance.manage'), 403);
        $sessions->setStatus($request->user(), $session, SessionStatus::from($status));

        return back()->with('status', 'Session is now '.$status.'.');
    }

    public function beacon(StoreBeaconRequest $request): RedirectResponse
    {
        $input = $request->validated();

        $location = Location::query()->where('uuid', $input['location_uuid'])->firstOrFail();

        Beacon::query()->updateOrCreate(
            ['organization_id' => $request->user()->organization_id, 'public_id' => $input['public_id']],
            ['location_id' => $location->getKey(), 'name' => $input['name'], 'is_active' => true],
        );

        return back()->with('status', 'Beacon saved. People can submit this identifier as evidence. Submitting it does not prove they are next to the beacon.');
    }

    public function wifi(StoreWifiNetworkRequest $request): RedirectResponse
    {
        $input = $request->validated();
        $bssid = $request->normalizedBssid();

        $location = Location::query()->where('uuid', $input['location_uuid'])->firstOrFail();

        WifiNetwork::query()->updateOrCreate(
            ['organization_id' => $request->user()->organization_id, 'bssid' => $bssid],
            ['location_id' => $location->getKey(), 'label' => $input['label'], 'is_active' => true],
        );

        return back()->with('status', 'Approved Wi-Fi network saved. Wi-Fi can support a check-in. It cannot confirm attendance alone. Browsers cannot read this address; a device client submits it.');
    }

    public function otp(IssueAttendanceOtpRequest $request): RedirectResponse
    {
        $input = $request->validated();

        $staff = StaffMember::query()->where('uuid', $input['staff_uuid'])->firstOrFail();
        $policy = WorkforcePolicy::resolve($request->user()->organization);

        if (! $policy->otp_enabled) {
            return back()->withErrors(['otp' => 'Turn on one-time codes in the workforce policy before issuing one.']);
        }

        $code = (string) random_int(100000, 999999);
        AttendanceOtp::query()->create([
            'organization_id' => $staff->organization_id,
            'staff_member_id' => $staff->getKey(),
            'issued_by' => $request->user()->getKey(),
            'code_hash' => hash('sha256', $code),
            'expires_at' => now()->addMinutes(10),
        ]);

        return back()->with('status', 'One-time code for '.$staff->name.': '.$code.'. It expires in 10 minutes and is not stored in readable form.');
    }
}
