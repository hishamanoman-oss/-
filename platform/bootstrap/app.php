<?php

use App\Presentation\Http\Middleware\AuthenticateConnectorInstance;
use App\Presentation\Http\Middleware\CheckPermission;
use App\Presentation\Http\Middleware\EnsurePlatformAdmin;
use App\Presentation\Http\Middleware\EnsureUserIsActive;
use App\Presentation\Http\Middleware\ResolveTenantContext;
use App\Presentation\Http\Middleware\SecurityHeadersMiddleware;
use App\Presentation\Http\Middleware\SetLocaleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeadersMiddleware::class,
            SetLocaleMiddleware::class,
            EnsureUserIsActive::class,
            ResolveTenantContext::class,
        ]);

        $middleware->api(append: [
            SecurityHeadersMiddleware::class,
        ]);

        $middleware->alias([
            'active.user' => EnsureUserIsActive::class,
            'tenant.context' => ResolveTenantContext::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'permission' => CheckPermission::class,
            'auth.connector' => AuthenticateConnectorInstance::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->report(function (Throwable $e): void {
            if (request()) {
                Log::channel('single')->error($e->getMessage(), [
                    'exception' => $e::class,
                    'path' => request()->path(),
                    'method' => request()->method(),
                    'deployment_mode' => config('platform.deployment_mode'),
                ]);
            }
        });
    })->create();
