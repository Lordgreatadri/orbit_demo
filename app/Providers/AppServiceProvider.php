<?php

namespace App\Providers;

use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Policies\InvitationPolicy;
use App\Policies\OrganizationPolicy;
use App\Policies\UserPolicy;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
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
        $this->configureQueueTenantReset();
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
        RateLimiter::for('invitation-accept', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('invitation-send', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->getKey() ?: $request->ip()));
    }

    private function configurePolicies(): void
    {
        Gate::policy(Organization::class, OrganizationPolicy::class);
        Gate::policy(Invitation::class, InvitationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
    }

    /**
     * Tenant context must never leak from one queued job to the next. Jobs that
     * process tenant data set the context explicitly from their own payload.
     */
    private function configureQueueTenantReset(): void
    {
        Queue::before(fn () => $this->app->make(TenantContext::class)->clear());
        Queue::after(fn () => $this->app->make(TenantContext::class)->clear());
        Queue::failing(fn () => $this->app->make(TenantContext::class)->clear());
    }
}
