<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            // Una sesión queda inválida si la contraseña de la cuenta cambió desde que se abrió (ver SesionesService).
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \App\Http\Middleware\EnsureUserIsActive::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // El navegador manda los avisos de la CSP sin token (ver CspReportController); el webhook del Central (/rukoz/webhook) no pasa
        // por el grupo `web`: lo autentica la firma.
        $middleware->validateCsrfTokens(except: ['csp-report']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
