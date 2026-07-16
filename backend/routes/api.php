<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\CategoryController;

// ========== 公开路由 ==========
Route::post('/auth/login', [AuthController::class, 'login']);
Route::post('/auth/register', [AuthController::class, 'register']);

// ========== 需要认证的路由 ==========
Route::middleware('auth:sanctum')->group(function () {
    // 认证相关
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // 用户管理（仅 admin）
    Route::middleware('role:admin')->group(function () {
        Route::apiResource('users', UserController::class);
    });

    // 分类管理
    Route::apiResource('categories', CategoryController::class);

    // 后续阶段将在这里添加：商品管理、出入库、盘点等路由
});
