<x-layouts.app title="My attendance">
    <x-page-header title="My attendance" description="The server decides the result. A button press alone does not mark you present." eyebrow="Attendance" />

    @error('attendance')
        <x-alert type="danger" title="Attendance was not recorded">{{ $message }}</x-alert>
    @enderror

    @if (! $staff)
        <x-empty-state title="No person record is linked to this account" description="Please contact your organisation administrator." />
    @else
        <x-card title="Today" :description="$staff->name.' · '.$staff->work_type->label()">
            @if ($todayRecords->isEmpty())
                <p class="text-sm text-fg-muted">No attendance recorded yet today.</p>
            @else
                <ul class="divide-y divide-edge">
                    @foreach ($todayRecords as $record)
                        <li class="py-3 first:pt-0 last:pb-0">
                            <p class="text-sm text-fg">
                                {{ $record->session?->name ?? 'Work period' }}
                                <x-badge :tone="$record->status->tone()">{{ $record->status->label() }}</x-badge>
                            </p>
                            <p class="mt-1 text-sm text-fg-muted">
                                @if ($record->work_date->toDateString() !== $today)
                                    Overnight from {{ $record->work_date->format('D j M') }} ·
                                @endif
                                In {{ $record->check_in_at?->timezone($timezone)->format('H:i') ?? '—' }} · Out {{ $record->check_out_at?->timezone($timezone)->format('H:i') ?? '—' }}
                            </p>
                            @if ($record->check_in_at && ! $record->check_out_at && $record->status->blocksCheckIn())
                                <form method="POST" action="{{ route('org.attendance.check-out') }}" class="mt-3">
                                    @csrf
                                    <input type="hidden" name="record" value="{{ $record->uuid }}">
                                    <x-button type="submit" size="sm" variant="secondary">Check out</x-button>
                                </form>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <section id="opened-sessions" class="scroll-mt-24 space-y-4">
            <div>
                <h2 class="text-base font-semibold text-fg">Open sessions</h2>
                <p class="text-sm text-fg-muted">Anyone signed in sees a live code here. Everyone at the session can use the current code until it changes. The same person cannot check in twice for that session.</p>
            </div>
            @if ($openSessions->isEmpty())
                <x-card title="No session is open">
                    <p class="text-sm text-fg-muted">When an administrator opens a session, the code appears in this section.</p>
                </x-card>
            @else
                @foreach ($openSessions as $open)
                    @php $code = $codes[$open->getKey()] ?? null; @endphp
                    <x-card :title="$open->name" :description="($open->location?->name ?? 'Any location').' · until '.$open->ends_at->timezone($timezone)->format('D j M, H:i').' · a new code every '.$open->qr_ttl_seconds.' seconds'">
                        @if ($code)
                            <div x-data="staffCode(@js($code['token']), @js($code['svg']), @js(route('org.attendance.sessions.code', $open)), {{ (int) $open->qr_ttl_seconds }}, {{ $open->require_location ? 'true' : 'false' }})" x-init="start()">
                                <div class="mx-auto rounded-2xl bg-white p-4 ring-1 ring-black/10" style="width:20rem">
                                    <div class="mx-auto" style="width:18rem;height:18rem" x-html="svg"></div>
                                </div>
                                <p class="mt-3 text-center text-sm text-fg-muted">New code in <span class="tabular-nums" x-text="remaining"></span>s</p>
                                <form method="POST" action="{{ route('org.attendance.check-in') }}" class="mt-4 flex flex-col items-center gap-3" x-ref="form">
                                    @csrf
                                    <input type="hidden" name="methods[]" value="qr">
                                    <input type="hidden" name="token" :value="token">
                                    <template x-if="needsLocation">
                                        <div>
                                            <input type="hidden" name="methods[]" value="gps">
                                            <input type="hidden" name="latitude" :value="latitude">
                                            <input type="hidden" name="longitude" :value="longitude">
                                            <input type="hidden" name="accuracy_m" :value="accuracy">
                                            <input type="hidden" name="captured_at" :value="capturedAt">
                                        </div>
                                    </template>
                                    <x-button type="button" size="sm" @click="submitCheckIn()">Check in with this code</x-button>
                                    <p class="text-center text-sm text-fg-muted" x-show="needsLocation">This session also needs your location. The button asks for it, then checks you in.</p>
                                    <p class="text-center text-sm text-danger" x-show="locationMessage" x-text="locationMessage"></p>
                                </form>
                            </div>
                        @else
                            <p class="text-sm text-fg-muted">The code is not available for this session right now.</p>
                        @endif
                    </x-card>
                @endforeach
            @endif
        </section>

        <x-card title="Beacon evidence" description="This page cannot detect a beacon automatically. Choose a beacon your organisation has registered. That submits its identifier as evidence. It does not prove your phone is next to the beacon. The server accepts it only when that identifier belongs to this organisation and is active.">
            @if ($beacons->isEmpty())
                <p class="text-sm text-fg-muted">No beacon is registered yet. Ask an administrator to add one on QR sessions.</p>
            @else
                <ul class="divide-y divide-edge">
                    @foreach ($beacons as $beacon)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3 first:pt-0 last:pb-0">
                            <div>
                                <p class="font-medium text-fg">{{ $beacon->name }}</p>
                                <p class="text-sm text-fg-muted">{{ $beacon->location?->name ?? 'No location' }}</p>
                            </div>
                            <form method="POST" action="{{ route('org.attendance.check-in') }}">
                                @csrf
                                <input type="hidden" name="methods[]" value="ble">
                                <input type="hidden" name="beacon_public_id" value="{{ $beacon->public_id }}">
                                <x-button type="submit" size="sm" variant="secondary">Submit {{ $beacon->name }}</x-button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card title="Office Wi-Fi" description="A browser cannot read the network address, so this page cannot check you in by Wi-Fi. Wi-Fi also cannot confirm attendance by itself. It can only support a session code, GPS, or a beacon when a device that can read the address sends it with that check-in.">
            @if ($networks->isEmpty())
                <p class="text-sm text-fg-muted">No office network is approved yet.</p>
            @else
                <ul class="divide-y divide-edge">
                    @foreach ($networks as $network)
                        <li class="py-3 first:pt-0 last:pb-0">
                            <p class="font-medium text-fg">{{ $network->label }}</p>
                            <p class="text-sm text-fg-muted">{{ $network->location?->name ?? 'No location' }} · supporting evidence only</p>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>

        <x-card title="Location or a pasted code" description="Use GPS when the site has a geofence. Paste a code only if a camera copied the check-in address and you are already signed in.">
            <form method="POST" action="{{ route('org.attendance.check-in') }}" class="grid gap-4" x-data="attendanceCheckIn()">
                @csrf
                <input type="hidden" name="methods[]" value="gps" x-bind:disabled="!latitude">
                <input type="hidden" name="methods[]" value="qr" x-bind:disabled="token === ''">
                <x-form.input name="token" label="Attendance code" hint="Optional. The current code expires quickly. Anyone eligible can use it until it changes. You can use it once for that session." x-model="token" />
                <x-form.select name="location_uuid" label="Location" :options="$locations->mapWithKeys(fn ($location) => [$location->uuid => $location->name])->prepend('Use my primary location', '')->all()" />
                @if (in_array($staff->work_type->value, ['remote', 'hybrid'], true))
                    <label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" name="methods[]" value="manual"> Remote check-in</label>
                @endif
                <input type="hidden" name="latitude" x-model="latitude">
                <input type="hidden" name="longitude" x-model="longitude">
                <input type="hidden" name="accuracy_m" x-model="accuracy">
                <input type="hidden" name="captured_at" x-model="capturedAt">
                <input type="hidden" name="device_hash" x-model="deviceHash">
                <p class="text-sm text-fg-muted" x-text="locationMessage"></p>
                <div class="flex flex-wrap gap-2">
                    <x-button type="button" variant="secondary" size="sm" @click="locate()">Use my location</x-button>
                    <x-button type="submit" size="sm">Submit check-in</x-button>
                </div>
            </form>
        </x-card>

        <x-card title="Recent days" :padding="false">
            <ul class="divide-y divide-edge">
                @forelse ($history as $record)
                    <li class="flex items-center justify-between gap-3 px-5 py-3 text-sm">
                        <span>{{ $record->work_date->toFormattedDateString() }}@if ($record->session) · {{ $record->session->name }}@endif</span>
                        <x-badge :tone="$record->status->tone()">{{ $record->status->label() }}</x-badge>
                    </li>
                @empty
                    <li class="px-5 py-4 text-sm text-fg-muted">No history yet.</li>
                @endforelse
            </ul>
        </x-card>
    @endif

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('staffCode', (token, svg, url, seconds, needsLocation) => ({
                    token, svg, url, seconds, needsLocation,
                    remaining: seconds,
                    latitude: '',
                    longitude: '',
                    accuracy: '',
                    capturedAt: '',
                    locationMessage: '',
                    start() {
                        if (!this.token) return;
                        setInterval(() => {
                            this.remaining -= 1;
                            if (this.remaining <= 0) {
                                this.remaining = this.seconds;
                                this.rotate();
                            }
                        }, 1000);
                    },
                    async rotate() {
                        const response = await fetch(this.url, {
                            method: 'POST',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                            body: JSON.stringify({ previous: this.token }),
                        });
                        if (!response.ok) return;
                        const body = await response.json();
                        this.token = body.token;
                        this.svg = body.svg;
                    },
                    submitCheckIn() {
                        if (!this.needsLocation) {
                            this.$refs.form.submit();
                            return;
                        }
                        if (!navigator.geolocation) {
                            this.locationMessage = 'This device has no location service.';
                            return;
                        }
                        navigator.geolocation.getCurrentPosition((position) => {
                            this.latitude = position.coords.latitude;
                            this.longitude = position.coords.longitude;
                            this.accuracy = Math.round(position.coords.accuracy);
                            this.capturedAt = new Date().toISOString();
                            this.$nextTick(() => this.$refs.form.submit());
                        }, () => {
                            this.locationMessage = 'Location permission was denied. This session needs it before check-in.';
                        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
                    },
                }));
                Alpine.data('attendanceCheckIn', () => ({
                    token: '',
                    latitude: '',
                    longitude: '',
                    accuracy: '',
                    capturedAt: '',
                    deviceHash: localStorage.getItem('orbit-device') || '',
                    locationMessage: 'Location is read only when you ask for it.',
                    init() {
                        if (!this.deviceHash) {
                            const bytes = crypto.getRandomValues(new Uint8Array(16));
                            this.deviceHash = Array.from(bytes, (b) => b.toString(16).padStart(2, '0')).join('');
                            localStorage.setItem('orbit-device', this.deviceHash);
                        }
                    },
                    locate() {
                        if (!navigator.geolocation) {
                            this.locationMessage = 'This device has no location service.';
                            return;
                        }
                        navigator.geolocation.getCurrentPosition((position) => {
                            this.latitude = position.coords.latitude;
                            this.longitude = position.coords.longitude;
                            this.accuracy = Math.round(position.coords.accuracy);
                            this.capturedAt = new Date().toISOString();
                            this.locationMessage = 'Location captured with about ' + this.accuracy + ' m accuracy. The server still checks the geofence.';
                        }, () => {
                            this.locationMessage = 'Location permission was denied or is unavailable.';
                        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
                    },
                }));
            });
        </script>
    @endpush
</x-layouts.app>
