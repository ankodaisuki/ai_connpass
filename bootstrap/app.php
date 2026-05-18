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
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 一時的な診断コード（確認後に削除）
        $exceptions->render(function (Throwable $e, $request) {
            return response(
                get_class($e).': '.$e->getMessage()."\nFile: ".$e->getFile().':'.$e->getLine(),
                500,
                ['Content-Type' => 'text/plain']
            );
        });
    })->create();
