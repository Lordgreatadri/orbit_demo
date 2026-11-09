<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workforce\DecideLeaveRequest;
use App\Http\Requests\Workforce\StoreLeaveRequest;
use App\Http\Requests\Workforce\StoreLeaveTypeRequest;
use App\Http\Requests\Workforce\UpdateLeaveTypeRequest;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Workforce\AttendanceException;
use App\Workforce\LeaveService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class LeaveController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($request->user()->can('leave.request') || $request->user()->can('leave.approve'), 403);

        $user = $request->user();
        $approve = $user->can('leave.approve');
        $staff = $user->staffMember;
        $timezone = $user->organization->timezone ?: 'UTC';
        $today = now($timezone)->toDateString();
        $year = (int) now($timezone)->year;
        $leave = app(LeaveService::class);

        $types = LeaveType::query()->where('is_active', true)->orderBy('name')->get();
        $balances = $staff === null
            ? collect()
            : LeaveBalance::query()
                ->where('staff_member_id', $staff->getKey())
                ->where('year', $year)
                ->get()
                ->keyBy('leave_type_id');

        $typeOptions = [];
        $typeMeta = [];

        foreach ($types as $type) {
            $row = $balances->get($type->getKey());
            $preview = [
                'entitled' => (float) ($row->entitled_days ?? $type->default_days ?? 0),
                'used' => (float) ($row->used_days ?? 0),
            ];
            $preview['remaining'] = $preview['entitled'] - $preview['used'];
            $requestable = ! $type->requires_balance || $preview['remaining'] > 0;

            if ($requestable) {
                $typeOptions[$type->uuid] = $type->name;
            }

            $typeMeta[$type->uuid] = [
                'name' => $type->name,
                'limited' => $type->requires_balance,
                'remaining' => $type->requires_balance ? $preview['remaining'] : null,
            ];
        }

        $own = $staff === null
            ? collect()
            : LeaveRequest::query()
                ->with(['staffMember:id,first_name,last_name', 'leaveType:id,name,requires_balance', 'decider:id,first_name,last_name'])
                ->where('staff_member_id', $staff->getKey())
                ->latest('starts_on')
                ->limit(50)
                ->get();

        $queue = $approve
            ? LeaveRequest::query()
                ->with(['staffMember:id,first_name,last_name', 'leaveType:id,name,requires_balance', 'decider:id,first_name,last_name'])
                ->where('status', LeaveStatus::Submitted->value)
                ->when($staff !== null, fn ($query) => $query->where('staff_member_id', '!=', $staff->getKey()))
                ->latest('starts_on')
                ->limit(50)
                ->get()
            : collect();

        $details = $own->concat($queue)->unique('id')->map(function (LeaveRequest $leaveRequest) use ($approve, $staff, $timezone, $leave, $year): array {
            $type = $leaveRequest->leaveType;
            $balance = null;

            if ($type?->requires_balance && $leaveRequest->staffMember !== null) {
                $balance = $leave->preview($leaveRequest->staffMember, $type, (int) $leaveRequest->starts_on->year ?: $year);
            }

            return [
                'uuid' => $leaveRequest->uuid,
                'name' => $leaveRequest->staffMember?->name ?? 'Staff member',
                'type' => $type?->name ?? 'Leave',
                'starts' => $leaveRequest->starts_on->format('m/d/Y'),
                'ends' => $leaveRequest->ends_on->format('m/d/Y'),
                'days' => $leaveRequest->dayCount(),
                'status' => $leaveRequest->status->label(),
                'tone' => $leaveRequest->status->tone(),
                'reason' => $leaveRequest->reason,
                'decisionNote' => $leaveRequest->decision_note,
                'decider' => $leaveRequest->decider?->name,
                'decidedAt' => $leaveRequest->decided_at?->timezone($timezone)->format('M j, Y g:i A'),
                'balance' => $balance === null ? null : $balance['remaining'],
                'canDecide' => $approve && $leaveRequest->status === LeaveStatus::Submitted,
                'decideUrl' => route('org.leave.decide', $leaveRequest),
                'own' => $staff !== null && (int) $leaveRequest->staff_member_id === (int) $staff->getKey(),
            ];
        })->values();

        return view('tenant.leave.index', [
            'pending' => $own->filter(fn (LeaveRequest $row) => $row->status === LeaveStatus::Submitted)->values(),
            'due' => $own->filter(fn (LeaveRequest $row) => $row->status === LeaveStatus::Approved && $row->ends_on->toDateString() >= $today)->values(),
            'approved' => $own->filter(fn (LeaveRequest $row) => $row->status === LeaveStatus::Approved && $row->ends_on->toDateString() < $today)->values(),
            'closed' => $own->filter(fn (LeaveRequest $row) => in_array($row->status, [LeaveStatus::Rejected, LeaveStatus::Cancelled], true))->values(),
            'queue' => $queue,
            'details' => $details,
            'types' => $types,
            'typeOptions' => $typeOptions,
            'typeMeta' => $typeMeta,
            'canApprove' => $approve,
            'canManageTypes' => $approve,
            'managedTypes' => $approve ? LeaveType::query()->orderBy('name')->get() : collect(),
            'staff' => $staff,
            'canRequest' => $staff !== null && $typeOptions !== [],
            'openRequest' => $request->session()->get('errors')?->hasAny(['leave', 'starts_on', 'ends_on', 'reason', 'leave_type_uuid', 'document']) ?? false,
            'timezone' => $timezone,
        ]);
    }

    public function type(StoreLeaveTypeRequest $request): RedirectResponse
    {
        $input = $request->validated();

        LeaveType::query()->create([
            'organization_id' => $request->user()->organization_id,
            'name' => $input['name'],
            'code' => $input['code'],
            'paid' => $input['paid'],
            'requires_balance' => $input['requires_balance'],
            'default_days' => (int) $input['default_days'],
            'is_active' => true,
        ]);

        return back()->with('status', 'Leave type saved.');
    }

    public function editType(Request $request, LeaveType $leaveType): View
    {
        abort_unless($request->user()->can('leave.approve'), 403);

        return view('tenant.leave.type', ['type' => $leaveType]);
    }

    public function updateType(UpdateLeaveTypeRequest $request, LeaveType $leaveType): RedirectResponse
    {
        $input = $request->validated();

        $leaveType->update([
            'name' => $input['name'],
            'code' => $input['code'],
            'paid' => $input['paid'],
            'requires_balance' => $input['requires_balance'],
            'default_days' => (int) ($input['default_days'] ?? $leaveType->default_days),
            'is_active' => $input['is_active'],
        ]);

        return redirect()->route('org.leave.index')->with('status', 'Leave type updated.');
    }

    public function store(StoreLeaveRequest $request, LeaveService $leave): RedirectResponse
    {
        $staff = $request->user()->staffMember;
        abort_if($staff === null, 422, 'No person record is linked to this account. Please contact your organisation administrator.');

        $input = $request->validated();

        try {
            $leave->submit(
                $request->user(),
                $staff,
                LeaveType::query()->where('uuid', $input['leave_type_uuid'])->where('is_active', true)->firstOrFail(),
                $input['starts_on'],
                $input['ends_on'],
                $input['reason'],
                $request->file('document'),
            );
        } catch (AttendanceException $exception) {
            return back()->withErrors(['leave' => $exception->getMessage()]);
        }

        return back()->with('status', 'Leave request submitted. It stays pending until an approver decides.');
    }

    public function decide(DecideLeaveRequest $request, LeaveRequest $leaveRequest, LeaveService $leave): RedirectResponse
    {
        try {
            $leave->decide($request->user(), $leaveRequest, $request->approved(), $request->validated('decision_note'));
        } catch (AttendanceException $exception) {
            return back()->withErrors(['leave' => $exception->getMessage()]);
        }

        return back()->with('status', 'Leave decision recorded.');
    }

    public function document(Request $request, LeaveRequest $leaveRequest): StreamedResponse
    {
        $user = $request->user();
        $owns = (int) $user->staffMember?->getKey() === (int) $leaveRequest->staff_member_id;
        abort_unless($owns || $user->can('leave.approve'), 403);
        abort_if($leaveRequest->document_path === null || ! Storage::disk('local')->exists($leaveRequest->document_path), 404);

        return Storage::disk('local')->download($leaveRequest->document_path);
    }
}
