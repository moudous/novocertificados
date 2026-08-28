<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\AllowGiEmbedding;
use App\Http\Middleware\EnsureGiPermission;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        Barryvdh\DomPDF\ServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        health: '/health',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(AllowGiEmbedding::class);
        $middleware->alias([
            'gi.permission' => EnsureGiPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
