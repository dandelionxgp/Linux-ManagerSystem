<?php

namespace App\Services;

use App\Exceptions\InsufficientStockException;
use App\Models\OperationLog;
use App\Models\Product;
use App\Models\StockIn;
use App\Models\StockInItem;
use App\Models\StockOut;
use App\Models\StockOutItem;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * 入库操作
     *
     * 使用数据库事务保证原子性：
     * 1. 生成入库单号 RK-YYYYMMDD-XXXX
     * 2. 创建入库单主记录
     * 3. 逐条创建入库明细 + 增量更新商品库存
     * 4. 记录操作日志
     *
     * @param  array   $data   包含 supplier, operator, remark, items
     * @param  int     $userId 操作人 ID
     * @param  string  $ip     操作 IP
     * @return StockIn
     */
    public function stockIn(array $data, int $userId, string $ip): StockIn
    {
        return DB::transaction(function () use ($data, $userId, $ip) {
            // 1. 生成入库单号
            $orderNo = $this->generateOrderNo('RK');

            // 2. 计算每条明细的小计和总金额
            $totalAmount = '0';
            foreach ($data['items'] as &$item) {
                $item['subtotal'] = bcmul((string) $item['quantity'], (string) $item['unit_price'], 2);
                $totalAmount = bcadd($totalAmount, $item['subtotal'], 2);
            }
            unset($item);

            // 3. 创建入库单主记录
            $stockIn = StockIn::create([
                'order_no'     => $orderNo,
                'supplier'     => $data['supplier'] ?? null,
                'total_amount' => $totalAmount,
                'operator'     => $data['operator'],
                'remark'       => $data['remark'] ?? null,
                'status'       => 1,
            ]);

            // 4. 逐条创建入库明细 + 更新库存
            foreach ($data['items'] as $item) {
                StockInItem::create([
                    'stock_in_id' => $stockIn->id,
                    'product_id'  => $item['product_id'],
                    'quantity'    => $item['quantity'],
                    'unit_price'  => $item['unit_price'],
                    'subtotal'    => $item['subtotal'],
                    'created_at'  => now(),
                ]);

                // 增量更新商品库存
                Product::where('id', $item['product_id'])
                    ->increment('current_stock', $item['quantity']);
            }

            // 5. 记录操作日志
            OperationLog::create([
                'user_id'     => $userId,
                'action'      => 'create',
                'module'      => 'stock_in',
                'target_id'   => $stockIn->id,
                'description' => "入库单 {$orderNo}，共 " . count($data['items']) . " 种商品，总金额 {$totalAmount}",
                'ip_address'  => $ip,
                'created_at'  => now(),
            ]);

            return $stockIn;
        });
    }

    /**
     * 出库操作
     *
     * 比入库多一步：库存校验 + 行锁防并发超卖
     *
     * @param  array   $data   包含 recipient, operator, remark, items
     * @param  int     $userId 操作人 ID
     * @param  string  $ip     操作 IP
     * @return StockOut
     *
     * @throws InsufficientStockException 库存不足时抛出
     */
    public function stockOut(array $data, int $userId, string $ip): StockOut
    {
        return DB::transaction(function () use ($data, $userId, $ip) {
            // 1. 预检库存：锁定所有涉及的商品行（悲观锁防并发）
            $productIds = array_column($data['items'], 'product_id');
            $products = Product::whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($data['items'] as $item) {
                $product = $products[$item['product_id']] ?? null;
                if (!$product) {
                    throw new \Exception("商品 ID {$item['product_id']} 不存在");
                }
                if ($product->current_stock < $item['quantity']) {
                    throw new InsufficientStockException(
                        "商品「{$product->name}」库存不足！当前库存: {$product->current_stock}，出库数量: {$item['quantity']}"
                    );
                }
            }

            // 2. 生成出库单号
            $orderNo = $this->generateOrderNo('CK');

            // 3. 计算总金额
            $totalAmount = '0';
            foreach ($data['items'] as &$item) {
                $item['subtotal'] = bcmul((string) $item['quantity'], (string) $item['unit_price'], 2);
                $totalAmount = bcadd($totalAmount, $item['subtotal'], 2);
            }
            unset($item);

            // 4. 创建出库单主记录
            $stockOut = StockOut::create([
                'order_no'  => $orderNo,
                'recipient' => $data['recipient'] ?? null,
                'operator'  => $data['operator'],
                'remark'    => $data['remark'] ?? null,
                'status'    => 1,
            ]);

            // 5. 逐条创建出库明细 + 扣减库存
            foreach ($data['items'] as $item) {
                StockOutItem::create([
                    'stock_out_id' => $stockOut->id,
                    'product_id'   => $item['product_id'],
                    'quantity'     => $item['quantity'],
                    'unit_price'   => $item['unit_price'],
                    'subtotal'     => $item['subtotal'],
                    'created_at'   => now(),
                ]);

                Product::where('id', $item['product_id'])
                    ->decrement('current_stock', $item['quantity']);
            }

            // 6. 记录操作日志
            OperationLog::create([
                'user_id'     => $userId,
                'action'      => 'create',
                'module'      => 'stock_out',
                'target_id'   => $stockOut->id,
                'description' => "出库单 {$orderNo}，领用人: {$data['recipient']}，共 " . count($data['items']) . " 种商品",
                'ip_address'  => $ip,
                'created_at'  => now(),
            ]);

            return $stockOut;
        });
    }

    /**
     * 冲销入库单
     *
     * 生成一笔抵消的出库单，将原入库的商品库存扣回。
     *
     * @param  StockIn $stockIn 被冲销的入库单
     * @param  int     $userId  操作人 ID
     * @param  string  $ip      操作 IP
     * @return StockOut
     *
     * @throws \Exception            已冲销时抛出
     * @throws InsufficientStockException 库存不足冲销时抛出
     */
    public function reverseStockIn(StockIn $stockIn, int $userId, string $ip): StockOut
    {
        if ($stockIn->status == 2) {
            throw new \Exception('该入库单已被冲销');
        }

        return DB::transaction(function () use ($stockIn, $userId, $ip) {
            // 预检库存：原入库数量可能已被部分出库
            $items = $stockIn->items;
            $productIds = $items->pluck('product_id');
            $products = Product::whereIn('id', $productIds)
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($items as $item) {
                $product = $products[$item->product_id] ?? null;
                $prodName = $product->name ?? '未知商品';
                $currentStock = $product->current_stock ?? 0;

                if ($currentStock < $item->quantity) {
                    throw new InsufficientStockException(
                        "商品「{$prodName}」当前库存 {$currentStock}，不足以冲销 {$item->quantity}"
                    );
                }
            }

            // 生成出库单号
            $orderNo = $this->generateOrderNo('CK');
            $totalAmount = $items->sum('subtotal');

            // 创建抵消出库单
            $stockOut = StockOut::create([
                'order_no'      => $orderNo,
                'recipient'     => "冲销入库单 {$stockIn->order_no}",
                'operator'      => $stockIn->operator,
                'remark'        => "冲销入库单 {$stockIn->order_no}",
                'status'        => 1,
                'reversed_from' => $stockIn->id,
            ]);

            // 逐条创建抵消出库明细 + 扣减库存
            foreach ($items as $item) {
                StockOutItem::create([
                    'stock_out_id' => $stockOut->id,
                    'product_id'   => $item->product_id,
                    'quantity'     => $item->quantity,
                    'unit_price'   => $item->unit_price,
                    'subtotal'     => $item->subtotal,
                    'created_at'   => now(),
                ]);

                Product::where('id', $item->product_id)
                    ->decrement('current_stock', $item->quantity);
            }

            // 更新原入库单状态为已冲销
            $stockIn->update(['status' => 2]);

            // 记录操作日志
            OperationLog::create([
                'user_id'     => $userId,
                'action'      => 'reverse',
                'module'      => 'stock_in',
                'target_id'   => $stockIn->id,
                'description' => "冲销入库单 {$stockIn->order_no}，生成出库单 {$orderNo}",
                'ip_address'  => $ip,
                'created_at'  => now(),
            ]);

            return $stockOut;
        });
    }

    /**
     * 冲销出库单
     *
     * 生成一笔抵消的入库单，将被出库的商品库存补回。
     *
     * @param  StockOut $stockOut 被冲销的出库单
     * @param  int      $userId   操作人 ID
     * @param  string   $ip       操作 IP
     * @return StockIn
     *
     * @throws \Exception 已冲销时抛出
     */
    public function reverseStockOut(StockOut $stockOut, int $userId, string $ip): StockIn
    {
        if ($stockOut->status == 2) {
            throw new \Exception('该出库单已被冲销');
        }

        return DB::transaction(function () use ($stockOut, $userId, $ip) {
            $orderNo = $this->generateOrderNo('RK');

            $items = $stockOut->items;
            $totalAmount = $items->sum('subtotal');

            // 创建抵消入库单
            $stockIn = StockIn::create([
                'order_no'      => $orderNo,
                'supplier'      => "冲销出库单 {$stockOut->order_no}",
                'total_amount'  => $totalAmount,
                'operator'      => $stockOut->operator,
                'remark'        => "冲销出库单 {$stockOut->order_no}",
                'status'        => 1,
                'reversed_from' => $stockOut->id,
            ]);

            // 逐条创建抵消入库明细 + 恢复库存
            foreach ($items as $item) {
                StockInItem::create([
                    'stock_in_id' => $stockIn->id,
                    'product_id'  => $item->product_id,
                    'quantity'    => $item->quantity,
                    'unit_price'  => $item->unit_price,
                    'subtotal'    => $item->subtotal,
                    'created_at'  => now(),
                ]);

                Product::where('id', $item->product_id)
                    ->increment('current_stock', $item->quantity);
            }

            // 更新原出库单状态为已冲销
            $stockOut->update(['status' => 2]);

            // 记录操作日志
            OperationLog::create([
                'user_id'     => $userId,
                'action'      => 'reverse',
                'module'      => 'stock_out',
                'target_id'   => $stockOut->id,
                'description' => "冲销出库单 {$stockOut->order_no}，生成入库单 {$orderNo}",
                'ip_address'  => $ip,
                'created_at'  => now(),
            ]);

            return $stockIn;
        });
    }

    /**
     * 生成单号：前缀-YYYYMMDD-序号(4位)
     *
     * 按日期独立计数，使用行锁防止并发生成重复单号。
     *
     * @param  string $prefix 'RK'（入库）或 'CK'（出库）
     * @return string
     */
    private function generateOrderNo(string $prefix): string
    {
        $date = now()->format('Ymd');
        $model = $prefix === 'RK' ? StockIn::class : StockOut::class;

        // 查询当天的最后一条单号，并使用行锁防并发
        $lastOrder = $model::where('order_no', 'like', "{$prefix}-{$date}-%")
            ->orderBy('order_no', 'desc')
            ->lockForUpdate()
            ->first();

        if ($lastOrder && preg_match('/-(\d{4})$/', $lastOrder->order_no, $m)) {
            $seq = intval($m[1]) + 1;
        } else {
            $seq = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $date, $seq);
    }
}
