<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Organizations\RegisterOrganization;
use App\Enums\OrganizationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterOrganizationRequest;
use DateTimeZone;
use Illuminate\Auth\Events\Registered;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * The only public account-creation endpoint. It always creates an organisation
 * and its responsible contact; there is no member/student/employee sign-up.
 */
class RegisteredOrganizationController extends Controller
{
    public function create(): View
    {
        return view('auth.register', [
            'organizationTypes' => OrganizationType::options(),
            'timezones' => $this->timezoneOptions(),
            'defaultTimezone' => config('orbit.default_timezone'),
        ]);
    }

    public function store(RegisterOrganizationRequest $request, RegisterOrganization $registerOrganization): RedirectResponse
    {
        $user = $registerOrganization->handle(
            $request->safe()->only([
                'organization_name', 'organization_type', 'timezone',
                'first_name', 'last_name', 'email', 'phone', 'password',
            ]),
            $request->ip(),
        );

        event(new Registered($user));

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()
            ->route('app.dashboard')
            ->with('status', __('Welcome to :app. Your organisation has been created.', ['app' => config('app.name')]));
    }

    /**
     * @return array<string, string>
     */
    private function timezoneOptions(): array
    {
        $options = [];

        foreach (DateTimeZone::listIdentifiers() as $identifier) {
            $options[$identifier] = str_replace('_', ' ', $identifier);
        }

        return $options;
    }
}
