@php
    $editing = $role->exists;
    $scopeValue = old('scope', $role->scope?->value ?? 'tenant');
    $selectedPermissions = old('permissions', $selected);
@endphp
<x-layouts.app :title="$editing ? 'Edit role · '.$role->label() : 'New role'">
    <x-page-header :title="$editing ? $role->label() : 'New role'" :description="$editing ? ($role->is_system ? 'System role: the name and scope are fixed; adjust the description and permission set.' : 'Scope is fixed after create so tenant and platform roles cannot be mixed. You can rename the role and change its permissions.') : 'Define a role and the permissions it grants. Platform permissions are only valid for platform-scoped roles.'" eyebrow="Roles">
        <x-button :href="route('admin.roles.index')" variant="ghost" size="sm"><x-icon name="chevron-left" class="size-4" /> All roles</x-button>
    </x-page-header>

    <form method="POST" action="{{ $editing ? route('admin.roles.update', $role) : route('admin.roles.store') }}" class="space-y-6" x-data="{ scope: @js($scopeValue) }">
        @csrf
        @if ($editing) @method('PUT') @endif

        <x-card title="Definition">
            <div class="grid gap-5 sm:grid-cols-2">
                <x-form.input name="name" label="Role name" :value="$role->name" required :readonly="$editing && $role->is_system" hint="Lower-case identifier, e.g. attendance_officer. Shown to users as a title." class="font-mono" />
                @if ($editing)
                    <x-form.field name="scope" label="Scope"><p class="h-10 rounded-lg border border-edge bg-surface-muted px-3 text-sm leading-10 text-fg">{{ $role->scope->label() }}</p></x-form.field>
                    <input type="hidden" name="scope" value="{{ $role->scope->value }}">
                @else
                    <x-form.select name="scope" label="Scope" :options="$scopes" :value="$scopeValue" required x-model="scope" hint="Tenant roles are visible to every organisation. Scope cannot be changed later." />
                @endif
                <div class="sm:col-span-2"><x-form.input name="description" label="Description" :value="$role->description" hint="One sentence shown to administrators when assigning the role." /></div>
            </div>
        </x-card>

        <x-card title="Permissions" description="Tick what this role grants. Modules are shown or hidden according to the selected scope.">
            <input type="hidden" name="permissions" value="">
            @foreach ($permissions as $module => $modulePermissions)
                @php $isPlatformModule = $module === 'platform'; @endphp
                <fieldset class="mb-5 last:mb-0" x-show="{{ $isPlatformModule ? "scope === 'platform'" : "scope === 'tenant'" }}" x-cloak>
                    <legend class="mb-2 flex items-center gap-2 text-xs font-semibold tracking-wide text-fg-subtle uppercase">
                        {{ $moduleLabels[$module] ?? \Illuminate\Support\Str::headline($module) }}
                        @if ($isPlatformModule)<x-badge tone="info" class="normal-case">Platform only</x-badge>@endif
                    </legend>
                    <div class="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                        @foreach ($modulePermissions as $permission)
                            <x-form.toggle name="permissions[]" :value="$permission->name" :checked="in_array($permission->name, $selectedPermissions, true)" :label="$permission->name" :description="$permission->description" />
                        @endforeach
                    </div>
                </fieldset>
            @endforeach
            @error('permissions')<p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
            @error('permissions.*')<p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>@enderror
        </x-card>

        <div class="flex justify-end gap-3">
            <x-button :href="route('admin.roles.index')" variant="ghost">Cancel</x-button>
            <x-button type="submit">{{ $editing ? 'Save role' : 'Create role' }}</x-button>
        </div>
    </form>
</x-layouts.app>
