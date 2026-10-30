<x-layouts.app title="User accounts" wide>
    <x-page-header title="User accounts" description="Login identities for your organisation and what each may do. Accounts are separate from staff records: a person may exist on the register without a login, and an account may be linked to a staff record.">
        @if ($canInvite)
            <x-button href="#invite" size="sm"><x-icon name="user-plus" class="size-4" /> Invite user</x-button>
        @endif
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-4 xl:col-span-2">
            <x-filter-bar :action="route('org.users.index')" :reset="route('org.users.index')">
                <x-form.inline-search placeholder="Name or e-mail…" />
                <x-form.inline-select name="role" label="Role" :options="$roles" />
                <x-form.inline-select name="status" label="Status" :options="$statuses" />
            </x-filter-bar>

            @if ($users->isEmpty())
                <x-empty-state title="No accounts match" description="Try clearing a filter." />
            @else
                <x-data-table :paginator="$users" caption="User accounts">
                    <thead>
                        <tr>
                            <th scope="col">Account</th>
                            <th scope="col" class="hidden md:table-cell">Roles</th>
                            <th scope="col" class="hidden lg:table-cell">Staff record</th>
                            <th scope="col">Status</th>
                            <th scope="col" class="hidden sm:table-cell">Last sign-in</th>
                            <th scope="col"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($users as $account)
                            <tr>
                                <td><x-user-chip :user="$account" :href="route('org.users.show', $account)" /></td>
                                <td class="hidden md:table-cell">
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($account->roles as $role)
                                            <x-badge tone="accent">{{ $role->label() }}</x-badge>
                                        @empty
                                            <span class="text-xs text-fg-subtle">None</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td class="hidden lg:table-cell">
                                    @if ($account->staffMember)
                                        <span class="font-mono text-xs">{{ $account->staffMember->staff_number }}</span>
                                    @else
                                        <span class="text-xs text-fg-subtle">Not linked</span>
                                    @endif
                                </td>
                                <td><x-badge :tone="$account->status->tone()">{{ $account->status->label() }}</x-badge></td>
                                <td class="hidden text-xs text-fg-muted sm:table-cell">{{ $account->last_login_at?->timezone($timezone)->diffForHumans() ?? 'Never' }}</td>
                                <td class="text-right"><x-button :href="route('org.users.show', $account)" variant="ghost" size="sm" aria-label="Manage {{ $account->name }}"><x-icon name="chevron-right" class="size-4" /></x-button></td>
                            </tr>
                        @endforeach
                    </tbody>
                </x-data-table>
            @endif
        </div>

        <div class="space-y-6">
            @if ($canInvite)
                <x-card id="invite" title="Invite a user" description="They receive an e-mail link valid for a limited time and choose their own password.">
                    <form method="POST" action="{{ route('org.invitations.store') }}" class="space-y-4">
                        @csrf
                        <x-form.input name="email" type="email" label="E-mail address" required autocomplete="off" placeholder="person@example.org" />
                        <x-form.select name="role" label="Role" :options="$invitableRoles" required placeholder="Choose a role" hint="Roles define what the account can do. You can refine permissions after they join." />
                        <x-button type="submit" class="w-full"><x-icon name="mail" class="size-4" /> Send invitation</x-button>
                    </form>
                </x-card>
            @endif

            @can('viewAny', \App\Models\Invitation::class)
                <x-card id="invitations" title="Open invitations" :description="$invitations->count().' not yet accepted'" :padding="false">
                    @if ($invitations->isEmpty())
                        <p class="p-5 text-sm text-fg-muted">No open invitations.</p>
                    @else
                        <ul class="divide-y divide-edge">
                            @foreach ($invitations as $invitation)
                                <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                                    <div class="min-w-0">
                                        <p class="truncate font-medium text-fg">{{ $invitation->email }}</p>
                                        <p class="text-xs text-fg-subtle">{{ \Illuminate\Support\Str::headline($invitation->role) }} · {{ $invitation->isPending() ? 'expires '.$invitation->expires_at->timezone($timezone)->diffForHumans() : $invitation->status->label() }}</p>
                                    </div>
                                    @if ($invitation->isPending() && $canInvite)
                                        <x-confirm-form :action="route('org.invitations.destroy', $invitation)" method="DELETE" :confirm="'Revoke the invitation for '.$invitation->email.'?'">
                                            <x-button type="submit" variant="ghost" size="sm" class="text-danger hover:bg-danger-soft" aria-label="Revoke invitation for {{ $invitation->email }}"><x-icon name="ban" class="size-4" /></x-button>
                                        </x-confirm-form>
                                    @else
                                        <x-badge tone="neutral">{{ $invitation->status->label() }}</x-badge>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-card>
            @endcan

            <x-card title="Roles in your organisation" description="What each role grants.">
                <p class="text-sm text-fg-muted">Roles are maintained by the platform; you assign them per account. <a href="{{ route('org.roles.index') }}" class="text-primary hover:underline">Review role definitions</a>.</p>
            </x-card>
        </div>
    </div>
</x-layouts.app>
