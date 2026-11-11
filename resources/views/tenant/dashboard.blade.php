<x-layouts.app title="Dashboard">
    @php
        $hour = now($organization->timezone)->hour;
        $greeting = $hour < 12 ? 'morning' : ($hour < 17 ? 'afternoon' : 'evening');
        $perspectiveLabel = match ($perspective) {
            'department' => 'Department overview',
            'personal' => $organization->isEducational() && $user->hasRole('student') ? 'Student workspace' : 'Your workspace',
            default => 'Organisation overview',
        };
    @endphp

    <x-page-header :title="'Good '.$greeting.', '.$user->first_name" :description="now($organization->timezone)->format('l j F Y').' · '.$scopeLabel" :eyebrow="$perspectiveLabel">
        @if ($perspective !== 'personal')
            @can('create', \App\Models\StaffMember::class)
                <x-button :href="route('org.staff.create')" variant="secondary" size="sm"><x-icon name="user-plus" class="size-4" /> Add staff</x-button>
            @endcan
            @can('create', \App\Models\Invitation::class)
                <x-button :href="route('org.users.index').'#invite'" size="sm"><x-icon name="plus" class="size-4" /> Invite user</x-button>
            @endcan
        @else
            @can('attendance.record')
                <x-button :href="route('org.attendance.mine')" size="sm">My attendance</x-button>
            @endcan
        @endif
    </x-page-header>

    @if ($organization->status === \App\Enums\OrganizationStatus::PendingVerification)
        <x-alert type="warning" title="Organisation pending verification">
            Your organisation is operational while verification is completed. You can set up departments, locations, staff, and user accounts now.
        </x-alert>
    @endif

    @if ($perspective === 'personal')
        @include('tenant.dashboard.personal')
    @else
        @include('tenant.dashboard.operations')
    @endif
</x-layouts.app>
