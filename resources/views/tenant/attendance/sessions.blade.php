<x-layouts.app title="QR sessions" wide>
    @php
        $composerOpen = $errors->has('starts_at') || $errors->has('ends_at') || $errors->has('qr_ttl_seconds') || $summary['total'] === 0;
        $locationOptions = $locations->mapWithKeys(fn ($location) => [$location->uuid => $location->name])->all();
        $statusOptions = collect(\App\Enums\SessionStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])->all();
    @endphp

    <div x-data="{ composer: {{ $composerOpen ? 'true' : 'false' }} }" class="space-y-8">
        <x-page-header title="QR sessions" description="Opening a session tells everyone who can check in the place and time. They scan the code on the session screen. Codes are random, short-lived, and stored only as a hash.">
            <x-button type="button" variant="secondary" size="sm" @click="composer = ! composer">
                <x-icon name="plus" class="size-4" />
                <span x-text="composer ? 'Hide form' : 'Open a session'">{{ $composerOpen ? 'Hide form' : 'Open a session' }}</span>
            </x-button>
        </x-page-header>

        <div x-show="composer" x-cloak>
            <x-card title="Open a session" description="The code rotates for the lifetime you set. A screenshot stops working when that code expires or is replaced.">
                <form method="POST" action="{{ route('org.attendance.sessions.store') }}" class="grid gap-4 sm:grid-cols-2">
                    @csrf
                    <x-form.input name="name" label="Name" required />
                    <x-form.select name="location_uuid" label="Location" :options="collect($locationOptions)->prepend('No fixed location', '')->all()" />
                    <x-form.input type="datetime-local" name="starts_at" label="Starts" required />
                    <x-form.input type="datetime-local" name="ends_at" label="Ends" required />
                    <x-form.input type="number" name="qr_ttl_seconds" label="Code lifetime (seconds)" value="30" min="10" max="120" />
                    <label class="flex items-center gap-2 self-end pb-2 text-sm text-fg"><input type="checkbox" name="require_location" value="1" class="size-4 rounded border-edge-strong text-primary"> Also require a geofence check</label>
                    <div class="sm:col-span-2"><x-button type="submit" size="sm">Activate session</x-button></div>
                </form>
            </x-card>
        </div>

        <section aria-labelledby="session-summary-heading">
            <h2 id="session-summary-heading" class="sr-only">Session totals</h2>
            <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
                <x-stat label="Open now" :value="number_format($summary['open'])" hint="Active and inside the time window" icon="qr" tone="success" />
                <x-stat label="Marked active" :value="number_format($summary['active'])" :hint="number_format($summary['total']).' '.($summary['total'] === 1 ? 'session' : 'sessions').' in total'" icon="check" />
                <x-stat label="Paused" :value="number_format($summary['paused'])" hint="Code is not being issued" icon="clock" tone="warning" />
                <x-stat label="Closed" :value="number_format($summary['closed'])" hint="Kept for the record, not loaded in full" icon="x" tone="neutral" />
            </div>
        </section>

        <x-filter-bar :action="route('org.attendance.sessions')" :reset="route('org.attendance.sessions')">
            <x-form.inline-search placeholder="Session name…" />
            <x-form.inline-select name="status" label="Status" :options="$statusOptions" placeholder="Any status" />
            <x-form.inline-select name="location" label="Location" :options="$locationOptions" placeholder="Any location" />
        </x-filter-bar>

        @if ($sessions->isEmpty())
            <x-empty-state
                :title="$summary['total'] === 0 ? 'No sessions yet' : 'No sessions match'"
                :description="$summary['total'] === 0 ? 'Open a session to show a rotating attendance code.' : 'Adjust the filters. Totals above still count every session.'"
            />
        @else
            <x-data-table :paginator="$sessions" caption="Attendance sessions">
                <thead>
                    <tr>
                        <th scope="col">Session</th>
                        <th scope="col">Status</th>
                        <th scope="col" class="hidden md:table-cell">Window</th>
                        <th scope="col" class="hidden lg:table-cell">Code</th>
                        <th scope="col" class="hidden xl:table-cell">Geofence</th>
                        <th scope="col" class="hidden xl:table-cell">Opened by</th>
                        <th scope="col"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($sessions as $session)
                        @php
                            $start = $session->starts_at->timezone($timezone);
                            $end = $session->ends_at->timezone($timezone);
                            $window = $start->isSameDay($end)
                                ? $start->format('D j M Y, H:i').' – '.$end->format('H:i')
                                : $start->format('D j M Y, H:i').' – '.$end->format('D j M Y, H:i');
                        @endphp
                        <tr>
                            <td>
                                <a class="font-medium text-fg hover:text-primary hover:underline" href="{{ route('org.attendance.sessions.show', $session) }}">{{ $session->name }}</a>
                                <span class="block text-xs text-fg-muted">{{ $session->location?->name ?? 'Any location' }}</span>
                            </td>
                            <td><x-badge :tone="$session->status->tone()">{{ $session->status->label() }}</x-badge></td>
                            <td class="hidden text-sm md:table-cell">{{ $window }}</td>
                            <td class="hidden text-sm tabular-nums lg:table-cell">{{ $session->qr_ttl_seconds }}s</td>
                            <td class="hidden text-sm xl:table-cell">{{ $session->require_location ? 'Required' : 'Not required' }}</td>
                            <td class="hidden text-sm xl:table-cell">{{ $session->creator?->name ?? '—' }}</td>
                            <td class="whitespace-nowrap text-right">
                                <span class="group relative inline-flex">
                                    <a href="{{ route('org.attendance.sessions.show', $session) }}" class="inline-flex size-9 items-center justify-center rounded-lg text-fg-muted hover:bg-surface-muted hover:text-primary focus-visible:outline-2" aria-label="Open {{ $session->name }}">
                                        <x-icon name="eye" class="size-4" />
                                    </a>
                                    <span aria-hidden="true" class="pointer-events-none absolute top-1/2 right-full z-20 mr-1.5 -translate-y-1/2 rounded-md bg-fg px-2 py-1 text-xs font-medium whitespace-nowrap text-surface opacity-0 shadow-lift transition-opacity group-hover:opacity-100 group-focus-within:opacity-100">Open session</span>
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </x-data-table>
        @endif

        <div class="grid gap-8 xl:grid-cols-2">
            <div class="space-y-6">
                <x-card title="Beacons" description="Register a beacon installed at a site. A person submits its identifier as evidence. That does not prove they are standing next to it. This list is paged.">
                    <form method="POST" action="{{ route('org.attendance.beacons.store') }}" class="grid gap-3">
                        @csrf
                        <x-form.input name="name" label="Name" required />
                        <x-form.input name="public_id" label="Advertised identifier" required />
                        <x-form.select name="location_uuid" label="Location" :options="$locationOptions" required />
                        <div><x-button type="submit" size="sm">Save beacon</x-button></div>
                    </form>
                </x-card>
                @if ($beacons->isEmpty())
                    <x-empty-state title="No beacons yet" description="Register a beacon that is installed at one of your sites." />
                @else
                    <x-data-table :paginator="$beacons" caption="Beacons">
                        <thead>
                            <tr>
                                <th scope="col">Beacon</th>
                                <th scope="col" class="hidden sm:table-cell">Location</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($beacons as $beacon)
                                <tr>
                                    <td>
                                        <p class="font-medium text-fg">{{ $beacon->name }}</p>
                                        <p class="truncate font-mono text-xs text-fg-subtle">{{ $beacon->public_id }}</p>
                                    </td>
                                    <td class="hidden sm:table-cell">{{ $beacon->location?->name ?? '—' }}</td>
                                    <td><x-badge :tone="$beacon->is_active ? 'success' : 'neutral'">{{ $beacon->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-data-table>
                @endif
            </div>

            <div class="space-y-6">
                <x-card title="Approved Wi-Fi" description="Wi-Fi can support another method. It cannot confirm attendance by itself, and the browser cannot read the BSSID.">
                    <form method="POST" action="{{ route('org.attendance.wifi.store') }}" class="grid gap-3">
                        @csrf
                        <x-form.input name="label" label="Label" required />
                        <x-form.input name="bssid" label="BSSID" required />
                        <x-form.select name="location_uuid" label="Location" :options="$locationOptions" required />
                        <div><x-button type="submit" size="sm">Save network</x-button></div>
                    </form>
                </x-card>
                @if ($networks->isEmpty())
                    <x-empty-state title="No approved networks yet" description="A saved address can support a check-in. It cannot confirm attendance alone." />
                @else
                    <x-data-table :paginator="$networks" caption="Approved Wi-Fi networks">
                        <thead>
                            <tr>
                                <th scope="col">Network</th>
                                <th scope="col" class="hidden sm:table-cell">Location</th>
                                <th scope="col">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($networks as $network)
                                <tr>
                                    <td>
                                        <p class="font-medium text-fg">{{ $network->label }}</p>
                                        <p class="font-mono text-xs text-fg-subtle">{{ $network->bssid }}</p>
                                    </td>
                                    <td class="hidden sm:table-cell">{{ $network->location?->name ?? '—' }}</td>
                                    <td><x-badge :tone="$network->is_active ? 'success' : 'neutral'">{{ $network->is_active ? 'Active' : 'Inactive' }}</x-badge></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </x-data-table>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
