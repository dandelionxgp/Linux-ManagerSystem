<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\InventoryController;
use App\Http\Controllers\Api\LogController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StockInController;
use App\Http\Controllers\Api\StockOutController;
use App\Http\Controllers\Api\StockQueryController;
use App\Http\Controllers\Api\UserController;

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

    // 商品管理（所有认证用户可查看，admin/manager 可新增编辑删除）
    Route::apiResource('products', ProductController::class);
    Route::post('products/import', [ProductController::class, 'import'])->middleware('role:admin,manager');

    // 入库管理（仅 admin + manager）
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('stock-ins', [StockInController::class, 'index']);
        Route::post('stock-ins', [StockInController::class, 'store']);
        Route::get('stock-ins/{stockIn}', [StockInController::class, 'show']);
        Route::post('stock-ins/{stockIn}/reverse', [StockInController::class, 'reverse']);
    });

    // 出库管理（仅 admin + manager）
    Route::middleware('role:admin,manager')->group(function () {
        Route::get('stock-outs', [StockOutController::class, 'index']);
        Route::post('stock-outs', [StockOutController::class, 'store']);
        Route::get('stock-outs/{stockOut}', [StockOutController::class, 'show']);
        Route::post('stock-outs/{stockOut}/reverse', [StockOutController::class, 'reverse']);
    });

    // 库存查询（所有认证用户）
    Route::get('/stock/query', [StockQueryController::class, 'query']);
    Route::get('/stock/alerts', [StockQueryController::class, 'alerts']);
    Route::get('/stock/flow', [StockQueryController::class, 'flow']);

    // 盘点管理（所有认证用户可查看，admin/manager 可新建/录入/确认）
    Route::get('inventories', [InventoryController::class, 'index']);
    Route::get('inventories/{inventory}', [InventoryController::class, 'show']);
    Route::middleware('role:admin,manager')->group(function () {
        Route::post('inventories', [InventoryController::class, 'store']);
        Route::put('inventories/{inventory}/items', [InventoryController::class, 'updateItems']);
        Route::post('inventories/{inventory}/confirm', [InventoryController::class, 'confirm']);
    });

    // 报表打印（所有认证用户）
    Route::get('/reports/stock-in/{id}/print', [ReportController::class, 'stockInPrint']);
    Route::get('/reports/stock-out/{id}/print', [ReportController::class, 'stockOutPrint']);
    Route::get('/reports/inventory/{id}/print', [ReportController::class, 'inventoryPrint']);
    Route::get('/reports/stock-summary', [ReportController::class, 'stockSummary']);

    // 仪表盘（所有认证用户）
    Route::get('/dashboard', [DashboardController::class, 'index']);

    // 操作日志（仅 admin）
    Route::middleware('role:admin')->group(function () {
        Route::get('/logs', [LogController::class, 'index']);
        Route::get('/logs/options', [LogController::class, 'options']);
    });
});
