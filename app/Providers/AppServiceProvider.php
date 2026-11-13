<?php

namespace App\Providers;

use App\Analytics\Insights\DeterministicInsightProvider;
use App\Contracts\DnsLookup;
use App\Contracts\OperationalInsightProvider;
use App\Contracts\PushNotifier;
use App\Contracts\SmsSender;
use App\Integrations\Dns\ArrayDnsLookup;
use App\Integrations\Dns\PhpDnsLookup;
use App\Integrations\Push\NullPushNotifier;
use App\Integrations\Sms\LogSmsSender;
use App\Listeners\RecordLoginEvent;
use App\Models\Department;
use App\Models\Invitation;
use App\Models\Location;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\Role;
use App\Models\SmsMessage;
use App\Models\SmsProvider;
use App\Models\StaffMember;
use App\Models\User;
use App\Policies\DepartmentPolicy;
use App\Policies\InvitationPolicy;
use App\Policies\LocationPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\SmsMessagePolicy;
use App\Policies\SmsProviderPolicy;
use App\Policies\StaffMemberPolicy;
use App\Policies\UserPolicy;
use App\Reports\ReportRegistry;
use App\Tenancy\BrandingService;
use App\Tenancy\Origin;
use App\Tenancy\TenantContext;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped (not singleton): flushed at the end of each HTTP request / Octane cycle.
        $this->app->scoped(TenantContext::class);
        $this->app->singleton(BrandingService::class);
        $this->app->singleton(ArrayDnsLookup::class);
        $this->app->bind(DnsLookup::class, function ($app) {
            return $app->environment('testing')
                ? $app->make(ArrayDnsLookup::class)
                : $app->make(PhpDnsLookup::class);
        });
        // Phone confirmation stays on the log channel. Platform SMS uses SmsTransmitter
        // and must not be bound here until that channel is explicitly switched on.
        $this->app->bind(SmsSender::class, LogSmsSender::class);
        $this->app->bind(PushNotifier::class, NullPushNotifier::class);
        $this->app->bind(OperationalInsightProvider::class, DeterministicInsightProvider::class);
        $this->app->singleton(ReportRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureModels();
        $this->configurePasswords();
        $this->configureRateLimiting();
        $this->configurePolicies();
        $this->configureRouteBindings();
        $this->configureEvents();
        $this->configureNotificationUrls();
        $this->configureBrandingShare();
    }

    private function configureModels(): void
    {
        // Surface N+1 and mass-assignment mistakes early in non-production environments.
        Model::shouldBeStrict(! $this->app->isProduction());
    }

    private function configurePasswords(): void
    {
        Password::defaults(fn () => Password::min(10)
            ->letters()
            ->numbers()
            ->when($this->app->isProduction(), fn (Password $rule) => $rule->uncompromised()));
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('contact', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(3)->by($request->ip()));
        RateLimiter::for('verification', fn (Request $request) => Limit::perMinute(6)->by($request->user()?->getKey() ?: $request->ip()));
        RateLimiter::for('phone-otp', fn (Request $request) => Limit::perMinute(5)->by($request->user()?->getKey() ?: $request->ip()));
        RateLimiter::for('invitation-accept', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('invitation-send', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->getKey() ?: $request->ip()));
        // Access-control writes (roles, permissions, status) are low-volume by nature.
        RateLimiter::for('access-write', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->getKey() ?: $request->ip()));
        RateLimiter::for('sms-webhook', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));
        RateLimiter::for('attendance', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->getKey() ?: $request->ip()));
        RateLimiter::for('workforce-write', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->getKey() ?: $request->ip()));
        RateLimiter::for('reports-export', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->getKey() ?: $request->ip()));
    }

    private function configurePolicies(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Invitation::class, InvitationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(StaffMember::class, StaffMemberPolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Location::class, LocationPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);
        Gate::policy(SmsProvider::class, SmsProviderPolicy::class);
        Gate::policy(SmsMessage::class, SmsMessagePolicy::class);
    }

    /**
     * Tenant screens address user accounts by UUID but must never resolve a
     * user from another organisation; a foreign or malformed UUID is a 404.
     * (StaffMember, Department, Location, Invitation are scoped by their global
     * scope; User is not tenant-owned by trait because platform accounts exist.)
     */
    private function configureRouteBindings(): void
    {
        Route::bind('orgUser', function (string $value): User {
            abort_unless(Str::isUuid($value), 404);

            $tenantId = $this->app->make(TenantContext::class)->id();
            abort_if($tenantId === null, 404);

            return User::query()
                ->where('organization_id', $tenantId)
                ->where('uuid', $value)
                ->firstOrFail();
        });
    }

    private function configureEvents(): void
    {
        Event::subscribe(RecordLoginEvent::class);
    }

    /**
     * Signed verification and reset links must be minted against the tenant
     * origin so they land on the same host the member will sign in on.
     */
    private function configureNotificationUrls(): void
    {
        VerifyEmail::createUrlUsing(function (object $notifiable): string {
            return $this->signedOnOrigin($notifiable, function () use ($notifiable): string {
                return URL::temporarySignedRoute('verification.verify', now()->addMinutes(60), [
                    'id' => $notifiable->getKey(),
                    'hash' => sha1($notifiable->getEmailForVerification()),
                ]);
            });
        });

        ResetPassword::createUrlUsing(function (object $notifiable, string $token): string {
            return $this->signedOnOrigin($notifiable, fn () => url(route('password.reset', [
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ], false)));
        });
    }

    private function signedOnOrigin(object $notifiable, callable $make): string
    {
        $origin = $notifiable instanceof User && $notifiable->organization
            ? Origin::of($notifiable->organization)
            : Origin::central();

        Origin::force($origin);

        try {
            return $make();
        } finally {
            Origin::restore();
        }
    }

    private function configureBrandingShare(): void
    {
        View::composer('*', function ($view): void {
            if ($view->offsetExists('workspaceBranding')) {
                return;
            }

            $view->with(
                'workspaceBranding',
                $this->app->make(BrandingService::class)->for($this->app->make(TenantContext::class)->organization()),
            );
        });
    }
}
