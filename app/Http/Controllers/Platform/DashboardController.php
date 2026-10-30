<?php

namespace App\Http\Controllers\Platform;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\LoginEvent;
use App\Models\Organization;
use App\Models\StaffMember;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Platform overview: tenant health, account totals, and security signals.
 * All figures are aggregate SQL; nothing here opens a tenant workspace.
 */
class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $orgsByStatus = Organization::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $usersByType = User::query()
            ->select(DB::raw('CASE WHEN organization_id IS NULL THEN \'platform\' ELSE \'tenant\' END as kind'), DB::raw('count(*) as total'))
            ->groupBy('kind')
            ->pluck('total', 'kind');

        $since24h = now()->subDay();
        $loginsLast24h = LoginEvent::query()
            ->where('created_at', '>=', $since24h)
            ->select('event', DB::raw('count(*) as total'))
            ->groupBy('event')
            ->pluck('total', 'event');

        $counts = [
            'organizations' => (int) $orgsByStatus->sum(),
            'organizations_active' => (int) ($orgsByStatus[OrganizationStatus::Active->value] ?? 0),
            'organizations_pending' => (int) ($orgsByStatus[OrganizationStatus::PendingVerification->value] ?? 0),
            'organizations_suspended' => (int) ($orgsByStatus[OrganizationStatus::Suspended->value] ?? 0),
            'organizations_closed' => (int) ($orgsByStatus[OrganizationStatus::Closed->value] ?? 0),
            'users_tenant' => (int) ($usersByType['tenant'] ?? 0),
            'users_platform' => (int) ($usersByType['platform'] ?? 0),
            'staff_records' => StaffMember::withoutTenantScope()->count(),
            'new_organizations_30d' => Organization::query()->where('created_at', '>=', now()->subDays(30))->count(),
            'logins_24h' => (int) ($loginsLast24h['login'] ?? 0),
            'failed_24h' => (int) ($loginsLast24h['failed'] ?? 0),
            'lockouts_24h' => (int) ($loginsLast24h['lockout'] ?? 0),
        ];

        $recentOrganizations = $user->can('platform.organizations.view')
            ? Organization::query()->withCount('users')->latest('id')->limit(6)->get()
            : collect();

        $recentActivity = $user->can('platform.audit.view')
            ? Activity::query()->with(['causer:id,first_name,last_name', 'organization:id,name'])->latest('id')->limit(8)->get()
            : collect();

        return view('platform.dashboard', [
            'user' => $user,
            'counts' => $counts,
            'recentOrganizations' => $recentOrganizations,
            'recentActivity' => $recentActivity,
        ]);
    }
}
