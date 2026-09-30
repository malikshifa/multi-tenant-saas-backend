<?php

use App\Http\Middleware\EnsureCentralPermission;
use App\Http\Middleware\EnsureTenantPermission;
use App\Http\Middleware\IdentifyTenant;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))

    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',

        then: function () {
            require base_path('routes/tenant.php');
        },
    )

    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([
            'central.permission' => EnsureCentralPermission::class,
            'tenant.permission' => EnsureTenantPermission::class,
            'tenant' => IdentifyTenant::class,
        ]);

        // The tenant database must be connected before Sanctum looks up the token.
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: IdentifyTenant::class,
        );

        // Authorize before route-model binding so unauthorized users cannot probe which IDs exist.
        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: EnsureCentralPermission::class,
        );

        $middleware->prependToPriorityList(
            before: SubstituteBindings::class,
            prepend: EnsureTenantPermission::class,
        );

    })

    ->withExceptions(function (Exceptions $exceptions): void {

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (
            AccessDeniedHttpException $e,
            Request $request
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'You do not have permission to perform this action.',
                ], 403);
            }
        });

        $exceptions->render(function (
            AuthenticationException $e,
            Request $request
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });

        $exceptions->render(function (
            AuthorizationException $e,
            Request $request
        ) {
            if ($request->is('api/*')) {
                return response()->json([
                    'message' => 'You do not have permission to perform this action.',
                ], 403);
            }
        });

        // Business-rule violations (insufficient stock, cancelled subscription, over
        // a plan's user quota, ...) are expected user-facing errors, not server bugs.
        // Symfony's HttpException (404, 429, 403, ...) also extends RuntimeException,
        // so those must be excluded here and left to their own status codes.
        $exceptions->render(function (
            RuntimeException $e,
            Request $request
        ) {
            if ($e instanceof HttpExceptionInterface) {
                return null;
            }

            if ($request->is('api/*')) {
                return response()->json([
                    'message' => $e->getMessage(),
                ], 422);
            }
        });

    })->create();
