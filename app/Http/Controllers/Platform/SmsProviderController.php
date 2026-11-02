<?php

namespace App\Http\Controllers\Platform;

use App\Actions\Sms\SaveSmsProvider;
use App\Http\Controllers\Controller;
use App\Http\Requests\Platform\SaveSmsProviderRequest;
use App\Http\Requests\Platform\SendSmsTestRequest;
use App\Integrations\Sms\Exceptions\InvalidPhoneNumber;
use App\Integrations\Sms\SmsProviderRegistry;
use App\Integrations\Sms\SmsText;
use App\Integrations\Sms\SmsTransmitter;
use App\Jobs\Sms\RefreshSmsProviderBalance;
use App\Models\SmsMessage;
use App\Models\SmsProvider;
use App\Support\Audit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use InvalidArgumentException;

class SmsProviderController extends Controller
{
    public function settings(): RedirectResponse
    {
        $this->authorize('viewAny', SmsProvider::class);

        return redirect()->route('admin.sms-providers.index');
    }

    public function index(SmsProviderRegistry $registry): RedirectResponse
    {
        $this->authorize('viewAny', SmsProvider::class);
        $registry->ensureRows();

        $provider = SmsProvider::query()->orderByDesc('is_active')->orderBy('name')->firstOrFail();

        return redirect()->route('admin.sms-providers.show', $provider);
    }

    public function show(SmsProviderRegistry $registry, SmsProvider $smsProvider): View
    {
        $this->authorize('view', $smsProvider);
        $registry->ensureRows();

        $providers = SmsProvider::query()->orderBy('name')->get();
        $adapter = $registry->get($smsProvider->key);
        $smsProvider->refresh();

        $fields = collect($adapter->credentialFields())->map(function (array $field) use ($smsProvider) {
            $field['configured'] = $smsProvider->hasCredential($field['key']);
            $field['display'] = $field['secret']
                ? $smsProvider->masked($field['key'])
                : $smsProvider->credential($field['key']);

            return $field;
        });

        return view('platform.sms-providers.show', [
            'providers' => $providers,
            'provider' => $smsProvider,
            'adapter' => $adapter,
            'fields' => $fields,
            'anyActive' => $providers->contains(fn (SmsProvider $provider) => $provider->is_active && $provider->enabled),
            'messages' => SmsMessage::query()
                ->where('sms_provider_id', $smsProvider->id)
                ->latest('id')
                ->paginate(15, ['id', 'uuid', 'to', 'status', 'purpose', 'provider_status', 'created_at'], 'messages')
                ->withQueryString(),
        ]);
    }

    public function update(SaveSmsProviderRequest $request, SaveSmsProvider $save, SmsProvider $smsProvider): RedirectResponse
    {
        $this->authorize('update', $smsProvider);
        $save($smsProvider, $request->validated(), $request->user());

        $message = $smsProvider->fresh()->is_active
            ? $smsProvider->name.' is the provider that sends platform SMS.'
            : $smsProvider->name.' was saved. With no provider active, messages are written to the log.';

        return redirect()
            ->route('admin.sms-providers.show', $smsProvider)
            ->with('status', $message);
    }

    public function health(Request $request, SmsProvider $smsProvider): RedirectResponse
    {
        $this->authorize('update', $smsProvider);

        if (! $smsProvider->enabled) {
            return back()->withErrors(['health' => 'Turn the provider on before checking it.']);
        }

        RefreshSmsProviderBalance::dispatchSync($smsProvider->id);
        $smsProvider->refresh();

        Audit::log('sms_provider.health_checked', $smsProvider, [
            'key' => $smsProvider->key,
            'health_status' => $smsProvider->health_status->value,
        ], $request->user());

        return back()->with('status', $smsProvider->name.' health is now '.$smsProvider->health_status->label().'.');
    }

    public function test(SendSmsTestRequest $request, SmsTransmitter $transmitter, SmsProvider $smsProvider): RedirectResponse
    {
        $this->authorize('update', $smsProvider);

        if (! $smsProvider->enabled) {
            return back()->withErrors(['to' => 'Turn the provider on before sending a test.'])->withInput();
        }

        try {
            $message = $transmitter->transmit(
                $request->string('to')->toString(),
                $request->string('body')->toString(),
                'operator-test:'.$smsProvider->uuid.':'.Str::uuid(),
                'operator_test',
                null,
                $smsProvider,
            );
        } catch (InvalidPhoneNumber|InvalidArgumentException $exception) {
            return back()->withErrors(['to' => SmsText::safe($exception->getMessage())])->withInput();
        }

        Audit::log('sms_provider.test_queued', $smsProvider, [
            'key' => $smsProvider->key,
            'sms_message_uuid' => $message->uuid,
            'to' => $message->maskedTo(),
        ], $request->user());

        return back()->with('status', 'Test message '.$message->uuid.' is '.$message->fresh()->status->label().'.');
    }

    public function regenerateWebhookToken(Request $request, SmsProvider $smsProvider): RedirectResponse
    {
        $this->authorize('update', $smsProvider);

        $credentials = $smsProvider->credentials ?? [];
        $credentials['webhook_token'] = Str::random(48);
        $smsProvider->credentials = $credentials;
        $smsProvider->save();

        Audit::log('sms_provider.webhook_token_regenerated', $smsProvider, [
            'key' => $smsProvider->key,
        ], $request->user());

        return back()->with('status', 'A new callback URL was created. Update it in the '.$smsProvider->name.' dashboard.');
    }
}
