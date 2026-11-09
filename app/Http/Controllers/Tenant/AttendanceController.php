<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\SessionStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workforce\CheckInAttendanceRequest;
use App\Http\Requests\Workforce\CorrectAttendanceRequest;
use App\Http\Requests\Workforce\FilterAttendanceRequest;
use App\Models\AttendanceRecord;
use App\Models\AttendanceSession;
use App\Models\Beacon;
use App\Models\Department;
use App\Models\Location;
use App\Models\StaffMember;
use App\Models\WifiNetwork;
use App\Support\CalendarDay;
use App\Workforce\AttendanceEngine;
use App\Workforce\AttendanceException;
use App\Workforce\QrSessionService;
use App\Workforce\WorkforceReport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    public function index(FilterAttendanceRequest $request): View
    {
        $filters = $request->validated();

        $organization = $request->user()->organization;
        $date = $filters['date'] ?? now($organization->timezone)->toDateString();

        $records = AttendanceRecord::query()
            ->with(['staffMember:id,uuid,first_name,last_name,staff_number', 'department:id,name', 'location:id,name', 'session:id,name'])
            ->where(fn ($query) => CalendarDay::constrain($query, 'work_date', $date))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($filters['department'] ?? null, function ($query, $uuid): void {
                $query->whereIn('department_id', Department::query()->where('uuid', $uuid)->select('id'));
            })
            ->orderByDesc('check_in_at')
            ->paginate(25)
            ->withQueryString();

        return view('tenant.attendance.index', [
            'records' => $records,
            'date' => $date,
            'timezone' => $organization->timezone,
            'departments' => Department::query()->orderBy('name')->get(['id', 'uuid', 'name']),
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(['id', 'uuid', 'name']),
            'staff' => StaffMember::query()->orderBy('last_name')->limit(200)->get(['id', 'uuid', 'first_name', 'last_name', 'staff_number']),
            'canManage' => $request->user()->can('attendance.manage'),
            'summary' => app(WorkforceReport::class)->day($organization, $date),
        ]);
    }

    public function mine(Request $request): View
    {
        abort_unless($request->user()->can('attendance.record'), 403);

        $staff = $request->user()->staffMember;
        $organization = $request->user()->organization;
        $today = now($organization->timezone)->toDateString();
        $openSessions = $this->openSessions();
        $sessions = app(QrSessionService::class);
        $codes = [];

        foreach ($openSessions as $open) {
            try {
                $raw = $sessions->issue($open);
                $codes[$open->getKey()] = [
                    'token' => $raw,
                    'svg' => $sessions->image($open, $raw),
                ];
            } catch (AttendanceException) {
                $codes[$open->getKey()] = null;
            }
        }

        return view('tenant.attendance.mine', [
            'staff' => $staff,
            'todayRecords' => $staff
                ? AttendanceRecord::query()->with('session:id,name')->where('staff_member_id', $staff->getKey())->tap(fn ($query) => CalendarDay::constrain($query, 'work_date', $today))->orderByDesc('check_in_at')->get()
                : collect(),
            'history' => $staff
                ? AttendanceRecord::query()->with('session:id,name')->where('staff_member_id', $staff->getKey())->orderByDesc('work_date')->orderByDesc('id')->limit(14)->get()
                : collect(),
            'timezone' => $organization->timezone,
            'locations' => Location::query()->where('is_active', true)->orderBy('name')->get(['uuid', 'name']),
            'openSessions' => $openSessions,
            'codes' => $codes,
            'beacons' => Beacon::query()->with('location:id,name')->where('is_active', true)->orderBy('name')->get(),
            'networks' => WifiNetwork::query()->with('location:id,name')->where('is_active', true)->orderBy('label')->get(),
        ]);
    }

    public function code(Request $request, AttendanceSession $session, QrSessionService $sessions): JsonResponse
    {
        abort_unless($request->user()->can('attendance.record'), 403);

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

    public function arrive(Request $request, string $token, QrSessionService $sessions): View
    {
        abort_unless($request->user()->can('attendance.record'), 403);

        return view('tenant.attendance.arrive', $this->arriveContext($request, $token, $sessions));
    }

    public function arriveStore(Request $request, string $token, AttendanceEngine $engine): RedirectResponse
    {
        abort_unless($request->user()->can('attendance.record'), 403);

        $staff = $request->user()->staffMember;
        abort_if($staff === null, 422, $this->unlinkedPersonMessage());

        $location = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['nullable', 'numeric', 'between:0,10000'],
            'captured_at' => ['nullable', 'date'],
        ]);

        $payload = [
            'methods' => ['qr'],
            'token' => $token,
        ];

        if (filled($location['latitude'] ?? null) && filled($location['longitude'] ?? null)) {
            $payload['methods'][] = 'gps';
            $payload['latitude'] = $location['latitude'];
            $payload['longitude'] = $location['longitude'];
            $payload['accuracy_m'] = $location['accuracy_m'] ?? null;
            $payload['captured_at'] = $location['captured_at'] ?? null;
        }

        try {
            $record = $engine->checkIn($request->user(), $staff, $payload);
        } catch (AttendanceException $exception) {
            return back()->withErrors(['attendance' => $exception->getMessage()])->with('outcome', $exception->reason);
        }

        return redirect()
            ->route('org.attendance.mine')
            ->with('status', 'Attendance recorded: '.$record->status->label().'.');
    }

    public function checkIn(CheckInAttendanceRequest $request, AttendanceEngine $engine): RedirectResponse
    {
        $payload = $request->validated();

        $staff = $request->user()->staffMember;
        abort_if($staff === null, 422, $this->unlinkedPersonMessage());

        try {
            $record = $engine->checkIn($request->user(), $staff, $payload);
        } catch (AttendanceException $exception) {
            return back()->withErrors(['attendance' => $exception->getMessage()])->with('outcome', $exception->reason);
        }

        return back()->with('status', 'Attendance recorded: '.$record->status->label().'.');
    }

    public function checkOut(Request $request, AttendanceEngine $engine): RedirectResponse
    {
        abort_unless($request->user()->can('attendance.record'), 403);
        $staff = $request->user()->staffMember;
        abort_if($staff === null, 422, $this->unlinkedPersonMessage());

        $record = $request->validate([
            'record' => ['nullable', 'uuid'],
        ])['record'] ?? null;

        try {
            $engine->checkOut($request->user(), $staff, $record);
        } catch (AttendanceException $exception) {
            return back()->withErrors(['attendance' => $exception->getMessage()]);
        }

        return back()->with('status', 'Check-out recorded.');
    }

    public function correct(CorrectAttendanceRequest $request, AttendanceEngine $engine): RedirectResponse
    {
        $input = $request->validated();

        $staff = StaffMember::query()->where('uuid', $input['staff_uuid'])->firstOrFail();
        $locationId = isset($input['location_uuid'])
            ? Location::query()->where('uuid', $input['location_uuid'])->value('id')
            : null;

        try {
            $engine->correct($request->user(), $staff, [
                'work_date' => $input['work_date'],
                'status' => $input['status'],
                'check_in_at' => $input['check_in_at'] ?? null,
                'check_out_at' => $input['check_out_at'] ?? null,
                'location_id' => $locationId,
                'note' => $input['note'] ?? null,
            ]);
        } catch (AttendanceException $exception) {
            return back()->withErrors(['attendance' => $exception->getMessage()]);
        }

        return back()->with('status', 'Attendance correction saved.');
    }

    /**
     * @return array<string, mixed>
     */
    private function arriveContext(Request $request, string $token, QrSessionService $sessions): array
    {
        $staff = $request->user()->staffMember;
        $organization = $request->user()->organization;
        $session = $sessions->openSessionForToken($token);
        $record = $staff && $session
            ? AttendanceRecord::query()
                ->where('staff_member_id', $staff->getKey())
                ->where('attendance_session_id', $session->getKey())
                ->whereNotNull('check_in_at')
                ->first()
            : null;

        return [
            'token' => $token,
            'session' => $session,
            'staff' => $staff,
            'sessionRecord' => $record,
            'timezone' => $organization->timezone,
        ];
    }

    private function unlinkedPersonMessage(): string
    {
        return 'No person record is linked to this account. Please contact your organisation administrator.';
    }

    /**
     * @return Collection<int, AttendanceSession>
     */
    private function openSessions(): Collection
    {
        return AttendanceSession::query()
            ->with('location:id,name')
            ->where('status', SessionStatus::Active->value)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->orderBy('ends_at')
            ->limit(5)
            ->get();
    }
}
