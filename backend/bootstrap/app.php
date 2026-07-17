<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\RoleMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // 库存不足异常 → 友好 JSON 响应
        $exceptions->renderable(function (\App\Exceptions\InsufficientStockException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'code'    => 1001,
                    'message' => $e->getMessage(),
                ]);
            }
        });

        // 验证异常 → 统一 JSON 格式
        $exceptions->renderable(function (\Illuminate\Validation\ValidationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'code'    => 422,
                    'message' => $e->validator->errors()->first(),
                    'errors'  => $e->validator->errors(),
                ], 422);
            }
        });

        // 通用异常兜底（仅 JSON 请求，生产环境不暴露细节）
        $exceptions->renderable(function (\Throwable $e, $request) {
            if ($request->expectsJson() && !$request->is('api/auth/*')) {
                $message = app()->environment('production')
                    ? '服务器错误'
                    : $e->getMessage();

                return response()->json([
                    'code'    => 500,
                    'message' => $message,
                ], 500);
            }
        });
    })->create();
