<x-layouts.app :title="$session->name">
    @php
        $start = $session->starts_at->timezone($timezone);
        $end = $session->ends_at->timezone($timezone);
        $window = $start->isSameDay($end)
            ? $start->format('D j M, H:i').'–'.$end->format('H:i')
            : $start->format('D j M, H:i').' – '.$end->format('D j M, H:i');
        $placeholder = '<p class="p-6 text-center text-sm" style="color:#111827">'.e($unavailable).'</p>';
    @endphp
    <x-page-header :title="$session->name" :description="($session->location?->name ?? 'No fixed location').' · '.$window.' · a new code every '.$session->qr_ttl_seconds.' seconds'">
        <x-badge :tone="$session->status->tone()">{{ $session->status->label() }}</x-badge>
    </x-page-header>

    <div class="mx-auto w-full max-w-lg space-y-6">
        <x-card title="Scan to check in" description="Point a phone camera at the code. It opens a one-tap check-in for the person already signed in on that phone. A new code on this screen expires only the previous code shown here.">
            <div x-data="rotatingQr(@js($token), @js($svg), @js(route('org.attendance.sessions.token', $session)), {{ (int) $session->qr_ttl_seconds }})" x-init="start()">
                <div class="mx-auto rounded-2xl bg-white p-4 ring-1 ring-black/10" style="width:20rem">
                    <div
                        class="mx-auto"
                        style="width:18rem;height:18rem"
                        x-html="svg || @js($placeholder)"
                    ></div>
                </div>
                <p class="mt-3 text-center text-sm text-fg-muted" x-show="token" x-cloak>New code in <span class="tabular-nums" x-text="remaining"></span>s</p>
                <p class="mt-1 text-center text-sm text-fg-muted">Keep the whole white square in the camera frame, about a hand's width away.</p>
                <details class="mt-4 text-sm">
                    <summary class="cursor-pointer text-fg-muted">Type the code instead</summary>
                    <p class="mt-2 break-all text-center font-mono text-xs text-fg-muted" x-text="token"></p>
                </details>
            </div>
            <div class="mt-4 flex flex-wrap gap-2">
                @if ($session->status->value === 'active')
                    <form method="POST" action="{{ route('org.attendance.sessions.status', [$session, 'paused']) }}">@csrf<x-button type="submit" size="sm" variant="secondary">Pause</x-button></form>
                @elseif ($session->status->value === 'paused')
                    <form method="POST" action="{{ route('org.attendance.sessions.status', [$session, 'active']) }}">@csrf<x-button type="submit" size="sm">Resume</x-button></form>
                @endif
                @if ($session->status->value !== 'closed')
                    <form method="POST" action="{{ route('org.attendance.sessions.status', [$session, 'closed']) }}">@csrf<x-button type="submit" size="sm" variant="danger">Close</x-button></form>
                @endif
            </div>
        </x-card>

        <x-card title="Who is told" description="The message names the session, place, and time. It does not include the rotating code.">
            <p class="text-sm text-fg-muted">Opening a session emails each active account that can record attendance. The link opens My attendance at the live code. Anyone signed in sees a code there, including at another branch or away from this screen. The current code can be used by everyone at the session until it changes. This screen remains the code to put on a shared display.</p>
        </x-card>
    </div>

    @push('scripts')
        <script>
            document.addEventListener('alpine:init', () => {
                Alpine.data('rotatingQr', (token, svg, url, seconds) => ({
                    token, svg, url, seconds,
                    remaining: seconds,
                    ticking: false,
                    start() {
                        if (this.token) {
                            this.tick();
                            return;
                        }
                        this.rotate();
                        setInterval(() => {
                            if (!this.token) this.rotate();
                        }, 5000);
                    },
                    tick() {
                        if (this.ticking) return;
                        this.ticking = true;
                        this.remaining = this.seconds;
                        setInterval(() => {
                            if (!this.token) return;
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
                        this.tick();
                    },
                }));
            });
        </script>
    @endpush
</x-layouts.app>
