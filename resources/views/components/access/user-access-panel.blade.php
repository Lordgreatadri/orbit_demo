{{--
    Shared roles / direct-permissions / status panel used by the tenant and
    platform user screens. Routes are passed in so the same markup serves both
    consoles. Only options the actor may grant are rendered; the server enforces
    the same boundary.
--}}
@props([
    'user',
    'actor',
    'canManage',
    'grantableRoles',
    'grantablePermissions',
    'moduleLabels',
    'rolesAction',
    'permissionsAction',
    'statusAction',
])

@php
    $isSelf = $actor->is($user);
    $directPermissionNames = $user->permissions->pluck('name')->all();
    $roleNames = $user->roles->pluck('name')->all();
@endphp

<x-card title="Roles" description="Roles bundle permissions. An account needs at least one role to do anything beyond self-service.">
    @if ($canManage && $grantableRoles->isNotEmpty())
        <form method="POST" action="{{ $rolesAction }}" class="space-y-3">
            @csrf
            @method('PUT')
            <input type="hidden" name="roles" value="">
            <div class="grid gap-2 sm:grid-cols-2">
                @foreach ($grantableRoles as $role)
                    <x-form.toggle name="roles[]" :value="$role->name" :checked="in_array($role->name, $roleNames, true)" :label="$role->label()" :description="$role->description" />
                @endforeach
            </div>
            @foreach ($user->roles->whereNotIn('name', $grantableRoles->pluck('name')) as $kept)
                <p class="flex items-center gap-2 text-xs text-fg-muted"><x-icon name="lock" class="size-3.5" /> <strong class="font-medium text-fg">{{ $kept->label() }}</strong> is outside your reach and will be kept.</p>
            @endforeach
            @error('roles')<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror
            @error('roles.*')<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror
            <div class="flex justify-end"><x-button type="submit" size="sm">Save roles</x-button></div>
        </form>
    @else
        <ul class="flex flex-wrap gap-1.5">
            @forelse ($user->roles as $role)
                <li><x-badge tone="accent" :title="$role->description">{{ $role->label() }}</x-badge></li>
            @empty
                <li class="text-sm text-fg-muted">No role assigned.</li>
            @endforelse
        </ul>
        @if ($isSelf)
            <p class="mt-3 text-xs text-fg-subtle">You cannot change your own access. Ask another administrator.</p>
        @endif
    @endif
</x-card>

<x-card title="Direct permissions" description="Fine-grained grants on top of roles, for exceptions. Prefer roles where possible.">
    @if ($canManage && $grantablePermissions->isNotEmpty())
        <form method="POST" action="{{ $permissionsAction }}" class="space-y-4">
            @csrf
            @method('PUT')
            <input type="hidden" name="permissions" value="">
            @foreach ($grantablePermissions as $module => $permissions)
                <fieldset>
                    <legend class="mb-2 text-xs font-semibold tracking-wide text-fg-subtle uppercase">{{ $moduleLabels[$module] ?? \Illuminate\Support\Str::headline($module) }}</legend>
                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach ($permissions as $permission)
                            @php $viaRole = $user->hasPermissionTo($permission->name) && ! in_array($permission->name, $directPermissionNames, true); @endphp
                            <x-form.toggle name="permissions[]" :value="$permission->name" :checked="in_array($permission->name, $directPermissionNames, true)" :label="$permission->name" :description="($permission->description ?? '').($viaRole ? ' (already granted by a role)' : '')" />
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
            @error('permissions')<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror
            @error('permissions.*')<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror
            <div class="flex justify-end"><x-button type="submit" size="sm">Save permissions</x-button></div>
        </form>
    @else
        @if ($user->permissions->isEmpty())
            <p class="text-sm text-fg-muted">No direct permissions. Everything comes from roles.</p>
        @else
            <ul class="flex flex-wrap gap-1.5">
                @foreach ($user->permissions as $permission)
                    <li><x-badge tone="neutral" :title="$permission->description">{{ $permission->name }}</x-badge></li>
                @endforeach
            </ul>
        @endif
    @endif
</x-card>

@if ($canManage)
    <x-card :title="$user->isActive() ? 'Suspend account' : 'Reactivate account'" :description="$user->isActive() ? 'Signs the person out everywhere and blocks sign-in until reactivated. Their staff record and history are kept.' : 'Allows the person to sign in again.'">
        <form method="POST" action="{{ $statusAction }}" class="space-y-3" onsubmit="return confirm(@js($user->isActive() ? 'Suspend '.$user->name.'?' : 'Reactivate '.$user->name.'?'));">
            @csrf
            @method('PATCH')
            <input type="hidden" name="status" value="{{ $user->isActive() ? 'suspended' : 'active' }}">
            <x-form.input name="reason" label="Reason (recorded in the audit trail)" :required="false" placeholder="Optional" />
            @error('status')<p class="text-sm text-danger" role="alert">{{ $message }}</p>@enderror
            <div class="flex justify-end">
                <x-button type="submit" :variant="$user->isActive() ? 'danger-outline' : 'primary'" size="sm">
                    <x-icon :name="$user->isActive() ? 'ban' : 'refresh'" class="size-4" /> {{ $user->isActive() ? 'Suspend' : 'Reactivate' }}
                </x-button>
            </div>
        </form>
    </x-card>
@endif
