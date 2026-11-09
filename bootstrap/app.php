<?php

use App\Http\Middleware\AdminMiddleware;
use App\Http\Middleware\SanitizeMethodOverride;
use App\Http\Middleware\StudentMiddleware;
use App\Http\Middleware\TeacherMiddleware;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(SanitizeMethodOverride::class);

        $middleware->alias([
            'teacher' => TeacherMiddleware::class,
            'student' => StudentMiddleware::class,
            'admin' => AdminMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (AuthenticationException $e, Request $request) {
            return response()->json([
                'message' => 'Unauthenticated.',
            ], 401);
        });

        // طلبات API بدون Accept: application/json بتحاول تعمل redirect إلى route('login')
        // غير الموجودة في تطبيق API — نرجع 401 JSON بدل 500
        $exceptions->render(function (RouteNotFoundException $e, Request $request) {
            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json([
                    'message' => 'Unauthenticated.',
                ], 401);
            }
        });
    })->create();
