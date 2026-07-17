<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\StockIn;
use App\Models\StockOut;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    /**
     * 入库单打印数据
     */
    public function stockInPrint(string $id)
    {
        $stockIn = StockIn::with('items.product:id,name,code,spec,unit')->findOrFail($id);

        return response()->json([
            'code' => 0,
            'data' => [
                'title'      => '入库单',
                'order_no'   => $stockIn->order_no,
                'supplier'   => $stockIn->supplier,
                'operator'   => $stockIn->operator,
                'total'      => $stockIn->total_amount,
                'created_at' => $stockIn->created_at->toDateTimeString(),
                'items'      => $stockIn->items->map(function ($i) {
                    return [
                        'code'       => $i->product->code ?? '',
                        'name'       => $i->product->name ?? '',
                        'spec'       => $i->product->spec ?? '',
                        'unit'       => $i->product->unit ?? '',
                        'quantity'   => $i->quantity,
                        'unit_price' => $i->unit_price,
                        'subtotal'   => $i->subtotal,
                    ];
                }),
            ],
        ]);
    }

    /**
     * 出库单打印数据
     */
    public function stockOutPrint(string $id)
    {
        $stockOut = StockOut::with('items.product:id,name,code,spec,unit')->findOrFail($id);

        return response()->json([
            'code' => 0,
            'data' => [
                'title'      => '出库单',
                'order_no'   => $stockOut->order_no,
                'recipient'  => $stockOut->recipient,
                'operator'   => $stockOut->operator,
                'created_at' => $stockOut->created_at->toDateTimeString(),
                'items'      => $stockOut->items->map(function ($i) {
                    return [
                        'code'       => $i->product->code ?? '',
                        'name'       => $i->product->name ?? '',
                        'spec'       => $i->product->spec ?? '',
                        'unit'       => $i->product->unit ?? '',
                        'quantity'   => $i->quantity,
                        'unit_price' => $i->unit_price,
                        'subtotal'   => $i->subtotal,
                    ];
                }),
            ],
        ]);
    }

    /**
     * 盘点报告打印数据
     */
    public function inventoryPrint(string $id)
    {
        $inventory = Inventory::with('items.product:id,name,code,unit')->findOrFail($id);

        $statusMap = [1 => '新建', 2 => '已录入', 3 => '已确认'];

        return response()->json([
            'code' => 0,
            'data' => [
                'title'        => '盘点报告',
                'order_no'     => $inventory->order_no,
                'operator'     => $inventory->operator,
                'status_text'  => $statusMap[$inventory->status] ?? '',
                'created_at'   => $inventory->created_at->toDateTimeString(),
                'confirmed_at' => $inventory->confirmed_at?->toDateTimeString(),
                'items'        => $inventory->items->map(function ($i) {
                    return [
                        'code'       => $i->product->code ?? '',
                        'name'       => $i->product->name ?? '',
                        'unit'       => $i->product->unit ?? '',
                        'system_qty' => $i->system_qty,
                        'actual_qty' => $i->actual_qty,
                        'diff_qty'   => $i->diff_qty,
                    ];
                }),
            ],
        ]);
    }

    /**
     * 库存汇总报表
     */
    public function stockSummary(Request $request)
    {
        $query = Product::with('category:id,name');

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        $products = $query->get();

        $totalValue = 0;
        $alertCount = 0;

        $items = $products->map(function ($p) use (&$totalValue, &$alertCount) {
            $stockValue = bcmul((string) $p->current_stock, (string) $p->purchase_price, 2);
            $totalValue = bcadd($totalValue, $stockValue, 2);

            if ($p->current_stock < $p->safety_stock) {
                $alertCount++;
            }

            return [
                'code'           => $p->code,
                'name'           => $p->name,
                'category'       => $p->category->name ?? '',
                'spec'           => $p->spec,
                'unit'           => $p->unit,
                'current_stock'  => $p->current_stock,
                'safety_stock'   => $p->safety_stock,
                'purchase_price' => $p->purchase_price,
                'stock_value'    => $stockValue,
            ];
        });

        return response()->json([
            'code' => 0,
            'data' => [
                'title'       => '库存汇总报表',
                'date'        => now()->toDateString(),
                'total_types' => $products->count(),
                'total_value' => $totalValue,
                'alert_count' => $alertCount,
                'items'       => $items,
            ],
        ]);
    }
}
