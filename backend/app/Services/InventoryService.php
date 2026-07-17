<?php

namespace App\Services;

use App\Models\Inventory;
use App\Models\InventoryItem;
use App\Models\OperationLog;
use App\Models\Product;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    /**
     * 创建盘点单
     *
     * 根据盘点范围（全部 or 按分类）自动生成盘点明细，
     * system_qty 为创建时当前库存的快照值。
     */
    public function create(array $data, int $userId, string $ip): Inventory
    {
        return DB::transaction(function () use ($data, $userId, $ip) {
            // 1. 生成盘点单号 PD-YYYYMMDD-XXXX
            $date      = now()->format('Ymd');
            $lastOrder = Inventory::where('order_no', 'like', "PD-{$date}-%")
                ->orderBy('order_no', 'desc')
                ->lockForUpdate()
                ->first();

            $seq = 1;
            if ($lastOrder && preg_match('/-(\d{4})$/', $lastOrder->order_no, $m)) {
                $seq = intval($m[1]) + 1;
            }

            $orderNo = sprintf('PD-%s-%04d', $date, $seq);

            // 2. 创建盘点单主记录
            $inventory = Inventory::create([
                'order_no'    => $orderNo,
                'category_id' => $data['category_id'] ?? null,
                'status'      => 1,
                'operator'    => $data['operator'],
                'remark'      => $data['remark'] ?? null,
            ]);

            // 3. 确定盘点范围并生成明细（快照 system_qty）
            $productsQuery = Product::query();
            if (!empty($data['category_id'])) {
                $productsQuery->where('category_id', $data['category_id']);
            }
            $products = $productsQuery->get(['id', 'current_stock']);

            if ($products->isEmpty()) {
                throw new \Exception('盘点范围内没有商品');
            }

            $items = $products->map(function ($p) use ($inventory) {
                return [
                    'inventory_id' => $inventory->id,
                    'product_id'   => $p->id,
                    'system_qty'   => $p->current_stock,
                    'actual_qty'   => null,
                    'diff_qty'     => 0,
                    'created_at'   => now(),
                    'updated_at'   => now(),
                ];
            })->toArray();

            InventoryItem::insert($items);

            // 4. 记录操作日志
            OperationLog::create([
                'user_id'     => $userId,
                'action'      => 'create',
                'module'      => 'inventory',
                'target_id'   => $inventory->id,
                'description' => "创建盘点单 {$orderNo}，共 " . count($items) . " 种商品",
                'ip_address'  => $ip,
                'created_at'  => now(),
            ]);

            return $inventory;
        });
    }

    /**
     * 录入实盘数量
     *
     * 接收 items 数组: [{product_id, actual_qty}, ...]
     * 自动计算差异 = actual_qty - system_qty
     */
    public function enterItems(Inventory $inventory, array $items, int $userId, string $ip): Inventory
    {
        if ($inventory->status !== 1) {
            throw new \Exception('只能对"新建"状态的盘点单录入数量');
        }

        return DB::transaction(function () use ($inventory, $items, $userId, $ip) {
            foreach ($items as $item) {
                $systemQty = InventoryItem::where('inventory_id', $inventory->id)
                    ->where('product_id', $item['product_id'])
                    ->value('system_qty');

                $diff = (int) $item['actual_qty'] - (int) $systemQty;

                InventoryItem::where('inventory_id', $inventory->id)
                    ->where('product_id', $item['product_id'])
                    ->update([
                        'actual_qty' => $item['actual_qty'],
                        'diff_qty'   => $diff,
                    ]);
            }

            // 状态改为"已录入"
            $inventory->update(['status' => 2]);

            OperationLog::create([
                'user_id'     => $userId,
                'action'      => 'update',
                'module'      => 'inventory',
                'target_id'   => $inventory->id,
                'description' => "录入盘点单 {$inventory->order_no} 实盘数据",
                'ip_address'  => $ip,
                'created_at'  => now(),
            ]);

            return $inventory->fresh()->load('items.product');
        });
    }

    /**
     * 确认盘点并调整库存
     *
     * 核心逻辑：
     * - diff_qty > 0  → 盘盈 → 自动调用 StockService::stockIn()
     * - diff_qty < 0  → 盘亏 → 自动调用 StockService::stockOut()
     * - diff_qty = 0  → 无差异，跳过
     *
     * 复用 StockService 保证出入库单据完整、日志可追溯。
     */
    public function confirm(Inventory $inventory, int $userId, string $ip): Inventory
    {
        if ($inventory->status !== 2) {
            throw new \Exception('只能确认"已录入"状态的盘点单');
        }

        return DB::transaction(function () use ($inventory, $userId, $ip) {
            $stockService = app(StockService::class);
            $inventory->load('items.product');

            foreach ($inventory->items as $item) {
                $diff = (int) $item->diff_qty;
                if ($diff === 0) {
                    continue;
                }

                if ($diff > 0) {
                    // 盘盈 → 生成入库单
                    $stockService->stockIn([
                        'supplier' => '盘点盘盈',
                        'operator' => $inventory->operator,
                        'remark'   => "盘点单 {$inventory->order_no} 盘盈入库",
                        'items'    => [[
                            'product_id' => $item->product_id,
                            'quantity'   => $diff,
                            'unit_price' => $item->product->purchase_price ?? 0,
                        ]],
                    ], $userId, $ip);
                } else {
                    // 盘亏 → 生成出库单
                    $stockService->stockOut([
                        'recipient' => '盘点盘亏',
                        'operator'  => $inventory->operator,
                        'remark'    => "盘点单 {$inventory->order_no} 盘亏出库",
                        'items'     => [[
                            'product_id' => $item->product_id,
                            'quantity'   => abs($diff),
                            'unit_price' => $item->product->purchase_price ?? 0,
                        ]],
                    ], $userId, $ip);
                }
            }

            // 更新盘点单状态为"已确认"
            $inventory->update([
                'status'       => 3,
                'confirmed_at' => now(),
            ]);

            OperationLog::create([
                'user_id'     => $userId,
                'action'      => 'confirm',
                'module'      => 'inventory',
                'target_id'   => $inventory->id,
                'description' => "确认盘点单 {$inventory->order_no}，库存已自动调整",
                'ip_address'  => $ip,
                'created_at'  => now(),
            ]);

            return $inventory;
        });
    }
}
