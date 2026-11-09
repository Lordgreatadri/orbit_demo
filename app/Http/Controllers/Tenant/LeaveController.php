<?php

namespace App\Http\Controllers\Tenant;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Workforce\AdjustLeaveBalanceRequest;
use App\Http\Requests\Workforce\AmendLeaveRequest;
use App\Http\Requests\Workforce\CancelLeaveRequest;
use App\Http\Requests\Workforce\DecideLeaveRequest;
use App\Http\Requests\Workforce\StoreLeaveRequest;
use App\Http\Requests\Workforce\StoreLeaveRuleRequest;
use App\Http\Requests\Workforce\StoreLeaveTypeRequest;
use App\Http\Requests\Workforce\StoreStaffCategoryRequest;
use App\Http\Requests\Workforce\UpdateLeavePolicyRequest;
use App\Http\Requests\Workforce\UpdateLeaveTypeRequest;
use App\Models\Department;
use App\Models\LeaveBalance;
use App\Models\LeaveEntitlementRule;
use App\Models\LeavePolicy;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\StaffCategory;
use App\Models\StaffMember;
use App\Support\Audit;
use App\Support\CalendarDay;
use App\Workforce\AttendanceException;
use App\Workforce\LeavePlanner;
use App\Workforce\LeaveService;
use Illuminate\Contracts\Pagination\Paginator;
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
        $policyAdmin = $user->can('leave.policy');
        $staff = $user->staffMember;
        $timezone = $user->organization->timezone ?: 'UTC';
        $today = now($timezone)->toDateString();
        $planner = app(LeavePlanner::class);

        $types = LeaveType::query()->where('is_active', true)->orderBy('name')->get();

        $balances = $staff === null
            ? collect()
            : LeaveBalance::query()
                ->where('staff_member_id', $staff->getKey())
                ->where('period_start', '<', CalendarDay::following($today))
                ->where('period_end', '>=', $today)
                ->get()
                ->keyBy('leave_type_id');

        $typeOptions = [];
        $typeMeta = [];

        foreach ($types as $type) {
            if ($staff === null) {
                $typeMeta[$type->uuid] = [
                    'name' => $type->name,
                    'limited' => $type->requires_balance,
                    'remaining' => null,
                    'entitled' => null,
                ];

                continue;
            }

            $row = $balances->get($type->getKey());
            $policy = $planner->policyOn($type, $today);
            $periodStart = $policy === null ? substr($today, 0, 4).'-01-01' : $planner->period($policy, $today)[0];
            $entitled = $row?->entitled_days ?? $planner->entitledDays($staff, $type, $policy, $periodStart);
            $remaining = $row instanceof LeaveBalance ? $row->available() : (int) $entitled;
            $requestable = ! $type->requires_balance || $remaining > 0;

            if ($requestable) {
                $typeOptions[$type->uuid] = $type->name;
            }

            $typeMeta[$type->uuid] = [
                'name' => $type->name,
                'limited' => $type->requires_balance,
                'remaining' => $type->requires_balance ? $remaining : null,
                'entitled' => $type->requires_balance ? (int) $entitled : null,
            ];
        }

        $with = ['staffMember:id,first_name,last_name', 'leaveType:id,name,requires_balance', 'decider:id,first_name,last_name', 'policy:id,allow_self_approval,approval_mode'];
        $ownBase = $staff === null
            ? null
            : LeaveRequest::query()->with($with)->where('staff_member_id', $staff->getKey());

        $pending = $ownBase === null
            ? collect()
            : (clone $ownBase)->where('status', LeaveStatus::Submitted->value)->orderBy('starts_on')->paginate(15, ['*'], 'pending')->withQueryString();
        $due = $ownBase === null
            ? collect()
            : (clone $ownBase)->where('status', LeaveStatus::Approved->value)->where('ends_on', '>=', $today)->orderBy('starts_on')->paginate(15, ['*'], 'due')->withQueryString();
        $approved = $ownBase === null
            ? collect()
            : (clone $ownBase)->where('status', LeaveStatus::Approved->value)->where('ends_on', '<', $today)->orderByDesc('starts_on')->paginate(15, ['*'], 'approved')->withQueryString();
        $closed = $ownBase === null
            ? collect()
            : (clone $ownBase)->whereIn('status', [LeaveStatus::Rejected->value, LeaveStatus::Cancelled->value, LeaveStatus::Withdrawn->value])->orderByDesc('starts_on')->paginate(15, ['*'], 'closed')->withQueryString();

        $queue = $approve
            ? LeaveRequest::query()
                ->with(['staffMember:id,first_name,last_name,reports_to_id,department_id', 'leaveType:id,name,requires_balance', 'decider:id,first_name,last_name', 'policy:id,approval_mode,allow_self_approval'])
                ->where('status', LeaveStatus::Submitted->value)
                ->when($staff !== null, fn ($query) => $query->where('staff_member_id', '!=', $staff->getKey()))
                ->when(! $policyAdmin, function ($query) use ($staff): void {
                    $mine = $staff?->getKey() ?? 0;
                    $query->where(function ($outer) use ($mine): void {
                        $outer->whereDoesntHave('policy')
                            ->orWhereHas('policy', fn ($policy) => $policy->where('approval_mode', 'any_approver'))
                            ->orWhereHas('staffMember', function ($person) use ($mine): void {
                                $person->where('reports_to_id', $mine)
                                    ->orWhereNull('reports_to_id')
                                    ->orWhereHas('department', fn ($department) => $department->where('head_staff_member_id', $mine));
                            });
                    });
                })
                ->orderBy('starts_on')
                ->paginate(20, ['*'], 'queue')
                ->withQueryString()
            : collect();

        $visible = collect();

        foreach ([$pending, $due, $approved, $closed, $queue] as $page) {
            $visible = $visible->merge($page instanceof Paginator ? $page->getCollection() : $page);
        }

        $people = $visible->pluck('staff_member_id')->filter()->unique()->values();
        $balanceRows = $people->isEmpty()
            ? collect()
            : LeaveBalance::query()->whereIn('staff_member_id', $people)->get();

        $details = $visible->unique('id')->map(function (LeaveRequest $leaveRequest) use ($approve, $staff, $timezone, $balanceRows): array {
            $type = $leaveRequest->leaveType;
            $balance = null;

            if ($type?->requires_balance) {
                $row = $balanceRows->first(function (LeaveBalance $candidate) use ($leaveRequest): bool {
                    return (int) $candidate->staff_member_id === (int) $leaveRequest->staff_member_id
                        && (int) $candidate->leave_type_id === (int) $leaveRequest->leave_type_id
                        && $candidate->period_start->toDateString() <= $leaveRequest->starts_on->toDateString()
                        && $candidate->period_end->toDateString() >= $leaveRequest->starts_on->toDateString();
                });
                $balance = $row?->available();
            }

            $ownRequest = $staff !== null && (int) $leaveRequest->staff_member_id === (int) $staff->getKey();
            $selfAllowed = (bool) $leaveRequest->policy?->allow_self_approval;
            $mayDecide = $approve && (! $ownRequest || $selfAllowed);

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
                'balance' => $balance,
                'canDecide' => $mayDecide && $leaveRequest->status === LeaveStatus::Submitted,
                'canCancel' => $mayDecide && $leaveRequest->status === LeaveStatus::Approved,
                'canWithdraw' => $ownRequest && $leaveRequest->status === LeaveStatus::Submitted,
                'decideUrl' => route('org.leave.decide', $leaveRequest),
                'withdrawUrl' => route('org.leave.withdraw', $leaveRequest),
                'cancelUrl' => route('org.leave.cancel', $leaveRequest),
                'own' => $staff !== null && (int) $leaveRequest->staff_member_id === (int) $staff->getKey(),
            ];
        })->values();

        return view('tenant.leave.index', [
            'pending' => $pending,
            'due' => $due,
            'approved' => $approved,
            'closed' => $closed,
            'queue' => $queue,
            'details' => $details,
            'types' => $types,
            'typeOptions' => $typeOptions,
            'typeMeta' => $typeMeta,
            'canApprove' => $approve,
            'canManageTypes' => $policyAdmin,
            'managedTypes' => $policyAdmin ? LeaveType::query()->orderBy('name')->get() : collect(),
            'policies' => $policyAdmin ? LeavePolicy::query()->with(['leaveType:id,name', 'rules'])->orderBy('name')->get() : collect(),
            'categories' => $policyAdmin ? StaffCategory::query()->orderBy('name')->get() : collect(),
            'departments' => $policyAdmin
                ? Department::query()
                    ->when(strlen(trim((string) $request->query('unit', ''))) >= 2, fn ($query) => $query->where('name', 'like', '%'.trim((string) $request->query('unit')).'%'))
                    ->orderBy('name')
                    ->limit(60)
                    ->get(['uuid', 'name'])
                : collect(),
            'staff' => $staff,
            'canRequest' => $staff !== null && $typeOptions !== [],
            'openRequest' => $request->session()->get('errors')?->hasAny(['leave', 'starts_on', 'ends_on', 'reason', 'leave_type_uuid', 'document']) ?? false,
            'timezone' => $timezone,
            'adjustPeople' => $policyAdmin && strlen(trim((string) $request->query('person', ''))) >= 2
                ? StaffMember::query()->search(trim((string) $request->query('person')))->orderBy('last_name')->limit(30)->get(['uuid', 'first_name', 'last_name'])
                : collect(),
            'personFind' => trim((string) $request->query('person', '')),
        ]);
    }

    public function type(StoreLeaveTypeRequest $request): RedirectResponse
    {
        $input = $request->validated();

        $type = LeaveType::query()->create([
            'organization_id' => $request->user()->organization_id,
            'name' => $input['name'],
            'code' => $input['code'],
            'paid' => $input['paid'],
            'requires_balance' => $input['requires_balance'],
            'default_days' => (int) $input['default_days'],
            'is_active' => true,
        ]);
        app(LeavePlanner::class)->ensurePolicy($type);

        return back()->with('status', 'Leave type saved.');
    }

    public function editType(Request $request, LeaveType $leaveType): View
    {
        abort_unless($request->user()->can('leave.policy'), 403);

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

    public function withdraw(Request $request, LeaveRequest $leaveRequest, LeaveService $leave): RedirectResponse
    {
        abort_unless($request->user()->can('leave.request'), 403);

        try {
            $leave->withdraw($request->user(), $leaveRequest);
        } catch (AttendanceException $exception) {
            return back()->withErrors(['leave' => $exception->getMessage()]);
        }

        return back()->with('status', 'Leave request withdrawn.');
    }

    public function amend(AmendLeaveRequest $request, LeaveRequest $leaveRequest, LeaveService $leave): RedirectResponse
    {
        try {
            $leave->amend(
                $request->user(),
                $leaveRequest,
                $request->validated('starts_on'),
                $request->validated('ends_on'),
                $request->validated('reason'),
            );
        } catch (AttendanceException $exception) {
            return back()->withErrors(['leave' => $exception->getMessage()]);
        }

        return back()->with('status', 'Leave request updated.');
    }

    public function cancel(CancelLeaveRequest $request, LeaveRequest $leaveRequest, LeaveService $leave): RedirectResponse
    {
        try {
            $leave->cancel($request->user(), $leaveRequest, $request->validated('decision_note'));
        } catch (AttendanceException $exception) {
            return back()->withErrors(['leave' => $exception->getMessage()]);
        }

        return back()->with('status', 'Approved leave cancelled and the balance restored.');
    }

    public function updatePolicy(UpdateLeavePolicyRequest $request, LeavePolicy $leavePolicy): RedirectResponse
    {
        $input = $request->validated();
        $leavePolicy->update([
            'year_start_month' => (int) $input['year_start_month'],
            'year_start_day' => (int) $input['year_start_day'],
            'count_mode' => $input['count_mode'],
            'weekend_days' => $input['weekend_days'] ?? [],
            'exclude_non_working' => (bool) $input['exclude_non_working'],
            'approval_mode' => $input['approval_mode'],
            'allow_self_approval' => (bool) $input['allow_self_approval'],
        ]);

        Audit::log('leave.policy_updated', $leavePolicy, [
            'count_mode' => $input['count_mode'],
            'approval_mode' => $input['approval_mode'],
            'year_start' => $input['year_start_month'].'-'.$input['year_start_day'],
            'allow_self_approval' => (bool) $input['allow_self_approval'],
        ], $request->user());

        return back()->with('status', 'Leave policy saved. Open balances keep the entitlement they were given.');
    }

    public function rule(StoreLeaveRuleRequest $request): RedirectResponse
    {
        $input = $request->validated();
        $type = LeaveType::query()->where('uuid', $input['leave_type_uuid'])->firstOrFail();
        $policy = app(LeavePlanner::class)->ensurePolicy($type);

        $rule = LeaveEntitlementRule::query()->create([
            'organization_id' => $request->user()->organization_id,
            'leave_policy_id' => $policy->getKey(),
            'priority' => (int) $input['priority'],
            'staff_category_id' => isset($input['staff_category_uuid'])
                ? StaffCategory::query()->where('uuid', $input['staff_category_uuid'])->value('id')
                : null,
            'department_id' => isset($input['department_uuid'])
                ? Department::query()->where('uuid', $input['department_uuid'])->value('id')
                : null,
            'grade' => $input['grade'] ?? null,
            'employment_type' => $input['employment_type'] ?? null,
            'min_service_months' => $input['min_service_months'] ?? null,
            'max_service_months' => $input['max_service_months'] ?? null,
            'entitled_days' => (int) $input['entitled_days'],
            'effective_from' => $input['effective_from'],
            'effective_until' => $input['effective_until'] ?? null,
        ]);

        Audit::log('leave.rule_saved', $rule, [
            'priority' => (int) $input['priority'],
            'days' => (int) $input['entitled_days'],
        ], $request->user());

        return back()->with('status', 'Entitlement rule saved. It applies when a new balance is opened.');
    }

    public function adjust(AdjustLeaveBalanceRequest $request, LeaveService $leave): RedirectResponse
    {
        $input = $request->validated();

        try {
            $leave->adjust(
                $request->user(),
                StaffMember::query()->where('uuid', $input['staff_uuid'])->firstOrFail(),
                LeaveType::query()->where('uuid', $input['leave_type_uuid'])->firstOrFail(),
                (int) $input['days'],
                $input['reason'],
            );
        } catch (AttendanceException $exception) {
            return back()->withErrors(['leave' => $exception->getMessage()]);
        }

        return back()->with('status', 'Balance adjustment recorded.');
    }

    public function category(StoreStaffCategoryRequest $request): RedirectResponse
    {
        $input = $request->validated();
        StaffCategory::query()->create([
            'organization_id' => $request->user()->organization_id,
            'name' => $input['name'],
            'code' => $input['code'],
        ]);

        return back()->with('status', 'Staff category saved. People and entitlement rules can use it.');
    }
}
