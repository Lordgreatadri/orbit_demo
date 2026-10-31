<?php

use App\Http\Middleware\EnsureAccountIsOperational;
use App\Http\Middleware\EnsureCentralHost;
use App\Http\Middleware\EnsureIdentityIsVerified;
use App\Http\Middleware\EnsurePlatformUser;
use App\Http\Middleware\EnsureTenantHost;
use App\Http\Middleware\EnsureTenantUser;
use App\Http\Middleware\IdentifyTenant;
use App\Tenancy\TrustHostList;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // Route files are split by audience (ADR-0010):
        //   web.php        public pages
        //   auth.php       guest + verification flows
        //   app.php        account-neutral authenticated routes (/app)
        //   tenant.php     organisation workspace (/org, name org.*)
        //   superadmin.php platform console (/admin, name admin.*)
        web: [
            __DIR__.'/../routes/web.php',
            __DIR__.'/../routes/auth.php',
            __DIR__.'/../routes/app.php',
            __DIR__.'/../routes/tenant.php',
            __DIR__.'/../routes/superadmin.php',
        ],
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Hostname → tenant (ADR-0011). Must run before route-model binding so
        // OrganizationScope is active when `{organization:uuid}` resolves.
        $middleware->web(append: [
            IdentifyTenant::class,
        ]);
        $middleware->prependToPriorityList(SubstituteBindings::class, IdentifyTenant::class);

        $middleware->trustHosts(at: fn () => app(TrustHostList::class)->hosts());

        $middleware->alias([
            'account.operational' => EnsureAccountIsOperational::class,
            'identity.verified' => EnsureIdentityIsVerified::class,
            'tenant' => EnsureTenantUser::class,
            'platform' => EnsurePlatformUser::class,
            'tenant.host' => EnsureTenantHost::class,
            'central.host' => EnsureCentralHost::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('app.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
