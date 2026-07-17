<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Services\InventoryService;
use Illuminate\Http\Request;

class InventoryController extends Controller
{
    protected InventoryService $inventoryService;

    public function __construct(InventoryService $inventoryService)
    {
        $this->inventoryService = $inventoryService;
    }

    /**
     * 盘点单列表（分页 + 状态筛选）
     */
    public function index(Request $request)
    {
        $query = Inventory::withCount('items');

        if ($request->filled('keyword')) {
            $kw = $request->keyword;
            $query->where('order_no', 'like', "%{$kw}%");
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $list = $query->orderBy('id', 'desc')
            ->paginate($request->input('page_size', 15));

        return response()->json([
            'code' => 0,
            'data' => $list,
        ]);
    }

    /**
     * 创建盘点单
     */
    public function store(Request $request)
    {
        $request->validate([
            'category_id' => 'nullable|exists:categories,id',
            'operator'    => 'required|string|max:50',
            'remark'      => 'nullable|string|max:500',
        ]);

        try {
            $inventory = $this->inventoryService->create(
                $request->all(),
                auth()->id(),
                $request->ip()
            );

            return response()->json([
                'code'    => 0,
                'message' => '盘点单创建成功',
                'data'    => $inventory->load('items.product:id,name,code'),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 1001,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 盘点单详情（含所有明细）
     */
    public function show(string $id)
    {
        $inventory = Inventory::with('items.product:id,name,code,unit,spec')->findOrFail($id);

        return response()->json([
            'code' => 0,
            'data' => $inventory,
        ]);
    }

    /**
     * 录入实盘数量
     */
    public function updateItems(Request $request, string $id)
    {
        $request->validate([
            'items'              => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.actual_qty' => 'required|integer|min:0',
        ]);

        $inventory = Inventory::findOrFail($id);

        try {
            $result = $this->inventoryService->enterItems(
                $inventory,
                $request->items,
                auth()->id(),
                $request->ip()
            );

            return response()->json([
                'code'    => 0,
                'message' => '实盘数量已录入',
                'data'    => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 1001,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 确认盘点（自动调整库存）
     */
    public function confirm(Request $request, string $id)
    {
        $inventory = Inventory::findOrFail($id);

        try {
            $result = $this->inventoryService->confirm(
                $inventory,
                auth()->id(),
                $request->ip()
            );

            return response()->json([
                'code'    => 0,
                'message' => '盘点已确认，库存已自动调整',
                'data'    => $result,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'code'    => 1001,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
