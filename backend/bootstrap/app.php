<?php

use App\Http\Middleware\EnsureRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function (Application $app): void {
            $app->make(Router::class)
                ->middleware('api')
                ->group(__DIR__.'/../routes/telegram.php');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // The app is only reachable through the reverse proxy on the shared
        // network, so trust its forwarding headers — otherwise rate limiters
        // key on the proxy IP and HTTPS/scheme detection is wrong.
        $middleware->trustProxies(at: '*');

        $middleware->api(prepend: [
            HandleCors::class,
        ]);

        $middleware->alias([
            'role' => EnsureRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
