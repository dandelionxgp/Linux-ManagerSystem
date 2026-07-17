<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StockIn;
use App\Services\StockService;
use Illuminate\Http\Request;

class StockInController extends Controller
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * 入库单列表（分页 + 搜索）
     */
    public function index(Request $request)
    {
        $query = StockIn::with('items.product:id,name,code');

        // 按单号 / 供应商搜索
        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('order_no', 'like', "%{$keyword}%")
                  ->orWhere('supplier', 'like', "%{$keyword}%");
            });
        }

        $list = $query->orderBy('id', 'desc')
            ->paginate($request->input('page_size', 15));

        return response()->json([
            'code' => 0,
            'data' => $list,
        ]);
    }

    /**
     * 创建入库单
     */
    public function store(Request $request)
    {
        $request->validate([
            'supplier' => 'nullable|string|max:200',
            'operator' => 'required|string|max:50',
            'remark'   => 'nullable|string|max:500',
            'items'    => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        $stockIn = $this->stockService->stockIn(
            $request->all(),
            auth()->id(),
            $request->ip()
        );

        return response()->json([
            'code'    => 0,
            'message' => '入库成功',
            'data'    => $stockIn->load('items.product:id,name,code'),
        ]);
    }

    /**
     * 入库单详情
     */
    public function show(string $id)
    {
        $stockIn = StockIn::with('items.product')->findOrFail($id);

        return response()->json([
            'code' => 0,
            'data' => $stockIn,
        ]);
    }

    /**
     * 冲销入库单
     *
     * 生成一笔抵消的出库单，将原入库的商品库存扣回。
     */
    public function reverse(Request $request, string $id)
    {
        $stockIn = StockIn::findOrFail($id);

        try {
            $stockOut = $this->stockService->reverseStockIn(
                $stockIn,
                auth()->id(),
                $request->ip()
            );

            return response()->json([
                'code'    => 0,
                'message' => '入库单已冲销',
                'data'    => $stockOut->load('items.product:id,name,code'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 1001,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
