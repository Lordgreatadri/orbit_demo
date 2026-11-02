<x-layouts.app title="SMS providers">
    <x-page-header title="SMS providers" description="Platform settings for SMS. These messages are paid by ORBIT and are not counted against an organisation subscription. Phone confirmation codes stay in the application log." eyebrow="Settings">
        @if ($provider->is_active)
            <x-badge tone="success">{{ $provider->name }} is sending</x-badge>
        @endif
    </x-page-header>

    @unless ($anyActive)
        <x-alert type="warning" title="Delivery is logged" class="mb-5">
            No provider is active. Platform SMS is stored and written to the application log until you turn one on.
        </x-alert>
    @endunless

    <nav aria-label="SMS providers" class="mb-5 flex flex-wrap gap-2">
        @foreach ($providers as $tab)
            <a
                href="{{ route('admin.sms-providers.show', $tab) }}"
                @if ($tab->is($provider)) aria-current="page" @endif
                @class([
                    'inline-flex items-center gap-2 rounded-lg border px-3 py-2 text-sm font-medium focus-visible:outline-2',
                    'border-primary bg-primary-soft font-semibold text-fg' => $tab->is($provider),
                    'border-edge bg-surface text-fg-muted hover:border-edge-strong hover:text-fg' => ! $tab->is($provider),
                ])
            >
                {{ $tab->name }}
                @if ($tab->is_active)
                    <x-badge tone="success">Active</x-badge>
                @elseif ($tab->enabled)
                    <x-badge tone="info">On</x-badge>
                @else
                    <x-badge>Off</x-badge>
                @endif
            </a>
        @endforeach
    </nav>

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1.4fr)_minmax(18rem,0.8fr)]">
        <form method="POST" action="{{ route('admin.sms-providers.update', $provider) }}" class="space-y-5">
            @csrf
            @method('PUT')

            <x-card title="{{ $provider->name }}" description="{{ $adapter->summary() }} Documentation reviewed {{ $adapter->reviewedOn() }}.">
                <div class="space-y-5">
                    <p class="text-sm text-fg-muted">
                        <a href="{{ $adapter->documentationUrl() }}" class="text-primary underline-offset-2 hover:underline" rel="noopener noreferrer">Provider documentation</a>
                    </p>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <input type="hidden" name="enabled" value="0">
                        <x-form.checkbox name="enabled" :checked="$provider->enabled">
                            Enabled
                            <span class="mt-0.5 block text-xs text-fg-muted">Credentials are stored and this provider may be used.</span>
                        </x-form.checkbox>
                        <input type="hidden" name="is_active" value="0">
                        <x-form.checkbox name="is_active" :checked="$provider->is_active">
                            Use for delivery
                            <span class="mt-0.5 block text-xs text-fg-muted">Only one provider sends. Turning this on switches the others off.</span>
                        </x-form.checkbox>
                        <input type="hidden" name="is_fallback" value="0">
                        <x-form.checkbox name="is_fallback" :checked="$provider->is_fallback">
                            Fallback
                            <span class="mt-0.5 block text-xs text-fg-muted">Used once if the active provider rejects a message. Not used for operator tests.</span>
                        </x-form.checkbox>
                        @if ($adapter->supportsSandbox())
                            <input type="hidden" name="sandbox" value="0">
                            <x-form.checkbox name="sandbox" :checked="$provider->sandbox">
                                Sandbox host
                                <span class="mt-0.5 block text-xs text-fg-muted">Send to the provider sandbox instead of the live host.</span>
                            </x-form.checkbox>
                        @endif
                    </div>
                    <x-form.input name="sender_id" label="Sender ID or number" :value="$provider->sender_id" maxlength="34" autocomplete="off" hint="Approved sender ID, or for Twilio an E.164 number or Messaging Service SID (MG…)." />
                </div>
            </x-card>

            <x-card title="Credentials" description="Secrets stay encrypted. A saved secret is never shown in full. Leave a secret blank to keep it.">
                <div class="grid gap-5 sm:grid-cols-2">
                    @foreach ($fields as $field)
                        <x-form.input
                            :name="'credentials['.$field['key'].']'"
                            :label="$field['label']"
                            :type="$field['secret'] ? 'password' : 'text'"
                            :value="$field['secret'] ? null : $field['display']"
                            :hint="$field['secret'] && $field['configured'] ? 'Saved '.$field['display'].'. '.$field['hint'] : $field['hint']"
                            autocomplete="off"
                        />
                    @endforeach
                </div>
            </x-card>

            <x-card title="Timeouts and retries" description="A timeout or a provider 429/5xx is retried. A rejected sender, bad credentials, or an invalid number is not.">
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-form.input name="timeout_seconds" type="number" label="Timeout (seconds)" :value="$provider->timeout_seconds" min="3" max="30" required />
                    <x-form.input name="max_attempts" type="number" label="Attempts" :value="$provider->max_attempts" min="1" max="5" required hint="Includes the first try." />
                    <x-form.input name="requests_per_minute" type="number" label="Requests per minute" :value="$provider->requests_per_minute" min="1" max="600" required hint="A local guard. The provider may still enforce its own limit." />
                    <x-form.input name="backoff_seconds" label="Retry delays (seconds)" :value="implode(', ', $provider->backoffSchedule())" required hint="Comma-separated, for example 30, 120, 300." />
                    @if ($adapter->tracksSmsBundle())
                        <x-form.input name="low_bundle_threshold" type="number" step="1" min="0" label="SMS bundle threshold" :value="$provider->low_bundle_threshold" hint="E-mail super admins when the SMS bundle is at or below this number. A higher bundle does not send a warning. Leave blank to skip." />
                    @endif
                    @if ($adapter->tracksCashBalance())
                        <x-form.input name="low_balance_threshold" type="number" step="0.01" min="0" label="Cash threshold" :value="$provider->low_balance_threshold" hint="E-mail super admins when the cash or account balance is at or below this amount. It is not compared with the SMS bundle. Leave blank to skip." />
                    @endif
                </div>
            </x-card>

            <div class="flex justify-end">
                <x-button type="submit">Save {{ $provider->name }}</x-button>
            </div>
        </form>

        <div class="space-y-5">
            <x-card title="Health and balance">
                <dl class="space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-3">
                        <dt class="text-fg-muted">Health</dt>
                        <dd><x-badge :tone="$provider->health_status->tone()">{{ $provider->health_status->label() }}</x-badge></dd>
                    </div>
                    @if ($adapter->tracksSmsBundle())
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-fg-muted">SMS bundle</dt>
                            <dd class="font-medium text-fg">
                                @if ($provider->smsBundleReading() !== null)
                                    {{ $provider->smsBundleReading() }} SMS
                                @else
                                    Not checked
                                @endif
                            </dd>
                        </div>
                    @endif
                    @if ($adapter->tracksCashBalance())
                        <div class="flex items-center justify-between gap-3">
                            <dt class="text-fg-muted">Cash balance</dt>
                            <dd class="font-medium text-fg">
                                @if ($provider->cash_balance !== null)
                                    {{ $provider->cash_balance }} {{ $provider->cash_balance_unit }}
                                @else
                                    Not checked
                                @endif
                            </dd>
                        </div>
                    @endif
                    <div>
                        <dt class="text-fg-muted">Last result</dt>
                        <dd class="mt-1 text-fg">{{ $provider->health_message ?: 'No check has been run.' }}</dd>
                    </div>
                </dl>
                @error('health')
                    <p class="mt-3 text-sm text-danger" role="alert">{{ $message }}</p>
                @enderror
                <form method="POST" action="{{ route('admin.sms-providers.health', $provider) }}" class="mt-4">
                    @csrf
                    <x-button type="submit" variant="secondary" size="sm">Check balance and health</x-button>
                </form>
            </x-card>

            <x-card title="Callback URL" description="Paste this into the provider dashboard. It contains a secret. Treat it like a password.">
                <label for="webhook-url" class="sr-only">Callback URL</label>
                <input id="webhook-url" type="text" readonly value="{{ $provider->webhookUrl() }}" class="block w-full rounded-lg border border-edge bg-surface-muted px-3 py-2 text-xs text-fg" onclick="this.select()">
                <form method="POST" action="{{ route('admin.sms-providers.webhook-token', $provider) }}" class="mt-3" onsubmit="return confirm('Replace the callback URL? Delivery reports will fail until the provider dashboard is updated.')">
                    @csrf
                    <x-button type="submit" variant="danger-outline" size="sm">Regenerate callback URL</x-button>
                </form>
            </x-card>

            <x-card title="Send a test" description="Sends through this provider even when another one is active. The test is not a fallback hop.">
                <form method="POST" action="{{ route('admin.sms-providers.test', $provider) }}" class="space-y-4">
                    @csrf
                    <x-form.input name="to" label="Test number" placeholder="+233200000000" required autocomplete="off" />
                    <x-form.textarea name="body" label="Test message" value="ORBIT platform SMS test. You can ignore this message." required hint="Up to 640 characters." />
                    <x-button type="submit" variant="secondary" size="sm">Queue test message</x-button>
                </form>
            </x-card>
        </div>
    </div>

    <section class="mt-5 space-y-3" aria-labelledby="provider-messages">
        <div>
            <h2 id="provider-messages" class="text-base font-semibold text-fg">Recent messages</h2>
            <p class="text-sm text-fg-muted">Open a row to read the message and follow its status. Switching providers does not remove this history.</p>
        </div>
        @if ($messages->total() === 0)
            <x-empty-state title="No messages yet" description="A test or a later platform notification will appear here." />
        @else
            <x-data-table :paginator="$messages" caption="Recent messages">
                <thead>
                    <tr>
                        <th scope="col">When</th>
                        <th scope="col">To</th>
                        <th scope="col">Purpose</th>
                        <th scope="col">Status</th>
                        <th scope="col">Provider status</th>
                        <th scope="col"><span class="sr-only">Open</span></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($messages as $message)
                        <tr>
                            <td class="text-fg-muted"><a href="{{ route('admin.sms.messages.show', $message) }}" class="hover:text-primary hover:underline">{{ $message->created_at->timezone(config('app.timezone'))->format('d M Y H:i') }}</a></td>
                            <td>{{ $message->maskedTo() }}</td>
                            <td>{{ str_replace('_', ' ', $message->purpose) }}</td>
                            <td><x-badge :tone="$message->status->tone()">{{ $message->status->label() }}</x-badge></td>
                            <td class="text-fg-muted">{{ $message->provider_status ?: '—' }}</td>
                            <td class="text-right"><x-button :href="route('admin.sms.messages.show', $message)" variant="ghost" size="sm" aria-label="Open message to {{ $message->maskedTo() }}"><x-icon name="chevron-right" class="size-4" /></x-button></td>
                        </tr>
                    @endforeach
                </tbody>
            </x-data-table>
        @endif
    </section>
</x-layouts.app>
