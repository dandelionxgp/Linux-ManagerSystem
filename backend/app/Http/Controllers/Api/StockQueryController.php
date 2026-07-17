<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockInItem;
use App\Models\StockOutItem;
use Illuminate\Http\Request;

class StockQueryController extends Controller
{
    /**
     * 实时库存查询
     *
     * 支持筛选：关键词(名称/编码)、分类、仅看预警
     */
    public function query(Request $request)
    {
        $query = Product::with('category:id,name');

        // 关键词搜索（商品名称 or 编码）
        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where(function ($q) use ($kw) {
                $q->where('name', 'like', "%{$kw}%")
                  ->orWhere('code', 'like', "%{$kw}%");
            });
        }

        // 按分类筛选
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // 仅看预警商品（当前库存 < 安全库存）
        if ($request->boolean('alert_only')) {
            $query->whereColumn('current_stock', '<', 'safety_stock');
        }

        $list = $query->orderBy('id', 'desc')
            ->paginate($request->input('page_size', 15));

        return response()->json([
            'code' => 0,
            'data' => $list,
        ]);
    }

    /**
     * 库存预警列表（快捷接口，等同于 query?alert_only=1）
     */
    public function alerts(Request $request)
    {
        $request->merge(['alert_only' => true]);
        return $this->query($request);
    }

    /**
     * 出入库流水明细
     *
     * 可指定 product_id 查看某商品的完整流水，
     * 不指定则查看全部。按时间倒序排列。
     */
    public function flow(Request $request)
    {
        $request->validate([
            'product_id' => 'nullable|exists:products,id',
            'start_date' => 'nullable|date',
            'end_date'   => 'nullable|date',
        ]);

        // --- 入库明细 ---
        $inItems = StockInItem::with([
                'product:id,name,code',
                'stockIn:id,order_no,created_at'
            ])
            ->whereHas('stockIn', function ($q) {
                $q->where('status', 1);
            });

        // --- 出库明细 ---
        $outItems = StockOutItem::with([
                'product:id,name,code',
                'stockOut:id,order_no,created_at'
            ])
            ->whereHas('stockOut', function ($q) {
                $q->where('status', 1);
            });

        // 筛选条件
        if ($request->filled('product_id')) {
            $inItems->where('product_id', $request->product_id);
            $outItems->where('product_id', $request->product_id);
        }
        if ($request->filled('start_date')) {
            $inItems->where('created_at', '>=', $request->start_date);
            $outItems->where('created_at', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $inItems->where('created_at', '<=', $request->end_date . ' 23:59:59');
            $outItems->where('created_at', '<=', $request->end_date . ' 23:59:59');
        }

        // 分别取出并统一格式
        $ins = $inItems->get()->map(function ($item) {
            return [
                'type'       => '入库',
                'order_no'   => $item->stockIn->order_no ?? '',
                'product'    => $item->product->name ?? '',
                'code'       => $item->product->code ?? '',
                'quantity'   => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal'   => $item->subtotal,
                'created_at' => $item->created_at->toDateTimeString(),
            ];
        });

        $outs = $outItems->get()->map(function ($item) {
            return [
                'type'       => '出库',
                'order_no'   => $item->stockOut->order_no ?? '',
                'product'    => $item->product->name ?? '',
                'code'       => $item->product->code ?? '',
                'quantity'   => -$item->quantity,   // 出库用负数
                'unit_price' => $item->unit_price,
                'subtotal'   => -$item->subtotal,
                'created_at' => $item->created_at->toDateTimeString(),
            ];
        });

        // 合并后按时间倒序
        $merged = $ins->concat($outs)
            ->sortByDesc('created_at')
            ->values();

        // 手动分页
        $page     = max(1, (int) $request->input('page', 1));
        $pageSize = max(1, (int) $request->input('page_size', 20));
        $total    = $merged->count();
        $paged    = $merged->forPage($page, $pageSize)->values();

        return response()->json([
            'code' => 0,
            'data' => [
                'data'         => $paged,
                'total'        => $total,
                'current_page' => $page,
                'per_page'     => $pageSize,
            ],
        ]);
    }
}
