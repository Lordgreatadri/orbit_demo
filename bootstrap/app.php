<?php

use App\Http\Middleware\EnsureAccountIsOperational;
use App\Http\Middleware\EnsureIdentityIsVerified;
use App\Http\Middleware\SetTenantContext;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Tenant context is derived from the authenticated user on every web request.
        // It must run *before* route-model binding so the OrganizationScope is
        // already active when `{organization:uuid}` / `{invitation:uuid}` resolve.
        $middleware->web(append: [
            SetTenantContext::class,
        ]);
        $middleware->prependToPriorityList(SubstituteBindings::class, SetTenantContext::class);

        $middleware->alias([
            'account.operational' => EnsureAccountIsOperational::class,
            'identity.verified' => EnsureIdentityIsVerified::class,
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));
        $middleware->redirectUsersTo(fn () => route('app.dashboard'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
