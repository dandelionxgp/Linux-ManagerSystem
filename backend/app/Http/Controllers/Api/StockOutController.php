<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\InsufficientStockException;
use App\Http\Controllers\Controller;
use App\Models\StockOut;
use App\Services\StockService;
use Illuminate\Http\Request;

class StockOutController extends Controller
{
    protected StockService $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    /**
     * 出库单列表（分页 + 搜索）
     */
    public function index(Request $request)
    {
        $query = StockOut::with('items.product:id,name,code');

        // 按单号 / 领用人搜索
        if ($request->filled('keyword')) {
            $keyword = $request->keyword;
            $query->where(function ($q) use ($keyword) {
                $q->where('order_no', 'like', "%{$keyword}%")
                  ->orWhere('recipient', 'like', "%{$keyword}%");
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
     * 创建出库单
     */
    public function store(Request $request)
    {
        $request->validate([
            'recipient' => 'nullable|string|max:100',
            'operator'  => 'required|string|max:50',
            'remark'    => 'nullable|string|max:500',
            'items'     => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity'   => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
        ]);

        try {
            $stockOut = $this->stockService->stockOut(
                $request->all(),
                auth()->id(),
                $request->ip()
            );

            return response()->json([
                'code'    => 0,
                'message' => '出库成功',
                'data'    => $stockOut->load('items.product:id,name,code'),
            ]);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'code'    => 1001,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 出库单详情
     */
    public function show(string $id)
    {
        $stockOut = StockOut::with('items.product')->findOrFail($id);

        return response()->json([
            'code' => 0,
            'data' => $stockOut,
        ]);
    }

    /**
     * 冲销出库单
     *
     * 生成一笔抵消的入库单，将被出库的商品库存补回。
     */
    public function reverse(Request $request, string $id)
    {
        $stockOut = StockOut::findOrFail($id);

        try {
            $stockIn = $this->stockService->reverseStockOut(
                $stockOut,
                auth()->id(),
                $request->ip()
            );

            return response()->json([
                'code'    => 0,
                'message' => '出库单已冲销',
                'data'    => $stockIn->load('items.product:id,name,code'),
            ]);
        } catch (InsufficientStockException $e) {
            return response()->json([
                'code'    => 1001,
                'message' => $e->getMessage(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 1001,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
