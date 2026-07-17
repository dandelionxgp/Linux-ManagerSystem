<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OperationLog;
use App\Models\Product;
use App\Models\StockIn;
use App\Models\StockInItem;
use App\Models\StockOut;
use App\Models\StockOutItem;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * 仪表盘聚合数据
     *
     * 返回统计卡片数据 + 最近操作记录
     */
    public function index(Request $request)
    {
        $now = now();
        $monthStart = $now->copy()->startOfMonth();

        // 统计卡片
        $productCount    = Product::count();
        $monthlyStockIn  = StockIn::where('status', 1)
            ->where('created_at', '>=', $monthStart)
            ->count();
        $monthlyStockOut = StockOut::where('status', 1)
            ->where('created_at', '>=', $monthStart)
            ->count();
        $alertCount      = Product::whereColumn('current_stock', '<', 'safety_stock')->count();

        // 库存总价值（使用 bcmath）
        $products = Product::select('current_stock', 'purchase_price')->get();
        $totalStockValue = '0';
        foreach ($products as $p) {
            $totalStockValue = bcadd(
                $totalStockValue,
                bcmul((string) $p->current_stock, (string) $p->purchase_price, 2),
                2
            );
        }

        // 近 7 天每日入库/出库趋势
        $trendData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = $now->copy()->subDays($i);
            $dateStr = $date->format('Y-m-d');
            $dayLabel = $date->format('m/d');

            $dayIn = StockInItem::whereDate('created_at', $dateStr)->sum('quantity');
            $dayOut = StockOutItem::whereDate('created_at', $dateStr)->sum('quantity');

            $trendData[] = [
                'date'  => $dayLabel,
                'in'    => (int) $dayIn,
                'out'   => (int) $dayOut,
            ];
        }

        // 最近操作记录（最近 10 条）
        $recentLogs = OperationLog::with('user:id,username,real_name')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get()
            ->map(function ($log) {
                return [
                    'id'          => $log->id,
                    'username'    => $log->user->username ?? '',
                    'real_name'   => $log->user->real_name ?? '',
                    'action'      => $log->action,
                    'module'      => $log->module,
                    'description' => $log->description,
                    'created_at'  => $log->created_at->toDateTimeString(),
                ];
            });

        return response()->json([
            'code' => 0,
            'data' => [
                'stats' => [
                    'product_count'     => $productCount,
                    'monthly_stock_in'  => $monthlyStockIn,
                    'monthly_stock_out' => $monthlyStockOut,
                    'alert_count'       => $alertCount,
                    'total_stock_value' => $totalStockValue,
                ],
                'trend'       => $trendData,
                'recent_logs' => $recentLogs,
            ],
        ]);
    }
}
