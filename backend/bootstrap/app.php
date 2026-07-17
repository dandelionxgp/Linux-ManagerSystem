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
        // 未认证异常（auth:sanctum 中间件触发）→ 401 JSON
        $exceptions->renderable(function (\Illuminate\Auth\AuthenticationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'code'    => 401,
                    'message' => '未登录或 Token 已过期',
                ], 401);
            }
        });

        // 模型未找到 → 404 JSON
        $exceptions->renderable(function (\Illuminate\Database\Eloquent\ModelNotFoundException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'code'    => 404,
                    'message' => '请求的资源不存在',
                ], 404);
            }
        });

        // 未授权异常 → 403 JSON
        $exceptions->renderable(function (\Illuminate\Auth\Access\AuthorizationException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'code'    => 1003,
                    'message' => $e->getMessage() ?: '没有操作权限',
                ], 403);
            }
        });

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

        // 路由未匹配 → 404 JSON（Laravel 11 用 Symfony 异常）
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'code'    => 404,
                    'message' => '接口不存在',
                ], 404);
            }
        });

        // HTTP 异常统一处理（含 405 Method Not Allowed 等）
        $exceptions->renderable(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, $request) {
            if ($request->expectsJson()) {
                return response()->json([
                    'code'    => $e->getStatusCode(),
                    'message' => $e->getMessage() ?: '请求错误',
                ], $e->getStatusCode());
            }
        });

        // 通用异常兜底（仅 JSON 请求，生产环境不暴露细节）
        $exceptions->renderable(function (\Throwable $e, $request) {
            if ($request->expectsJson() && !$request->is('api/auth/*')) {
                $message = app()->environment('production')
                    ? '服务器内部错误'
                    : $e->getMessage();

                return response()->json([
                    'code'    => 5000,
                    'message' => $message,
                ], 500);
            }
        });
    })->create();
