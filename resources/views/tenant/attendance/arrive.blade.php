<x-layouts.app title="Check in">
    <x-page-header title="Check in" description="This page opened from the code on the session screen." />

    @error('attendance')
        <x-alert type="danger" title="Attendance was not recorded">{{ $message }}</x-alert>
    @enderror

    @if (! $staff)
        <x-empty-state title="No person record is linked to this account" description="Please contact your organisation administrator, then scan the code again." />
    @elseif (! $session)
        <x-empty-state title="This code is no longer valid" description="It may have expired or been replaced. Scan the code on the screen again. You need to already be signed in on this phone." />
    @elseif ($sessionRecord && $sessionRecord->status->blocksCheckIn())
        <x-card title="Already recorded" :description="$session->name">
            <p class="text-sm text-fg">Status <x-badge :tone="$sessionRecord->status->tone()">{{ $sessionRecord->status->label() }}</x-badge></p>
            <p class="mt-2 text-sm text-fg-muted">In {{ $sessionRecord->check_in_at?->timezone($timezone)->format('H:i') }}</p>
            <x-button :href="route('org.attendance.mine')" class="mt-4" size="sm" variant="secondary">Back to My attendance</x-button>
        </x-card>
    @else
        <x-card :title="$session->name" :description="($session->location?->name ?? 'Any location').' · until '.$session->ends_at->timezone($timezone)->format('D j M Y, H:i')">
            <p class="text-sm text-fg">Signed in as {{ $staff->name }}. Tap once before this code expires.</p>
            <form method="POST" action="{{ route('org.attendance.arrive.store', ['token' => $token]) }}" class="mt-4 space-y-3" x-data="arriveCheckIn({{ $session->require_location ? 'true' : 'false' }})" @submit="guard($event)">
                @csrf
                <input type="hidden" name="latitude" x-model="latitude">
                <input type="hidden" name="longitude" x-model="longitude">
                <input type="hidden" name="accuracy_m" x-model="accuracy">
                <input type="hidden" name="captured_at" x-model="capturedAt">
                @if ($session->require_location)
                    <p class="text-sm text-fg-muted">This session also checks that you are inside the approved area. The browser will ask for your location when you tap the button.</p>
                @endif
                <p class="text-sm text-fg-muted" x-text="locationMessage"></p>
                <x-button type="submit">Check in now</x-button>
            </form>
        </x-card>
    @endif

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('arriveCheckIn', (needsLocation) => ({
                    needsLocation,
                    ready: false,
                    latitude: '',
                    longitude: '',
                    accuracy: '',
                    capturedAt: '',
                    locationMessage: needsLocation ? 'Location is read only when you check in.' : '',
                    guard(event) {
                        if (!this.needsLocation || this.ready) return;
                        event.preventDefault();
                        if (!navigator.geolocation) {
                            this.locationMessage = 'This device has no location service.';
                            return;
                        }
                        navigator.geolocation.getCurrentPosition((position) => {
                            this.latitude = position.coords.latitude;
                            this.longitude = position.coords.longitude;
                            this.accuracy = Math.round(position.coords.accuracy);
                            this.capturedAt = new Date().toISOString();
                            this.ready = true;
                            this.$nextTick(() => this.$root.submit());
                        }, () => {
                            this.locationMessage = 'Location permission was denied or is unavailable.';
                        }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
                    },
                }));
            });
        </script>
    @endpush
</x-layouts.app>
