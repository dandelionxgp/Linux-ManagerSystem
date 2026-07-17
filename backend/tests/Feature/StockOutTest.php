<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StockOutTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 创建出库单成功 + 单号格式
     */
    public function test_can_create_stock_out(): void
    {
        $this->actingAsManager();
        $product = Product::create([
            'code' => 'SO001', 'name' => '出库测试', 'unit' => '件',
            'purchase_price' => '10.00', 'safety_stock' => 5, 'current_stock' => 100
        ]);

        $response = $this->postJson('/api/stock-outs', [
            'recipient' => '销售部',
            'operator'  => '张三',
            'items'     => [[
                'product_id' => $product->id, 'quantity' => 10, 'unit_price' => '12.00',
            ]],
        ]);

        $response->assertStatus(200)
            ->assertJson(['code' => 0, 'message' => '出库成功']);

        $orderNo = $response->json('data.order_no');
        $this->assertMatchesRegularExpression('/^CK-\d{8}-\d{4}$/', $orderNo);
    }

    /**
     * 出库后库存减少
     */
    public function test_stock_out_decreases_product_stock(): void
    {
        $this->actingAsManager();
        $product = Product::create([
            'code' => 'SO002', 'name' => '库存减少', 'unit' => '件',
            'purchase_price' => '10.00', 'safety_stock' => 5, 'current_stock' => 50
        ]);

        $this->postJson('/api/stock-outs', [
            'recipient' => '生产部',
            'operator'  => '李四',
            'items'     => [[
                'product_id' => $product->id, 'quantity' => 15, 'unit_price' => '10.00',
            ]],
        ])->assertStatus(200);

        $this->assertEquals(35, $product->fresh()->current_stock);
    }

    /**
     * 超出库存的出库被拦截 ⭐ 核心测试
     */
    public function test_cannot_stock_out_more_than_stock(): void
    {
        $this->actingAsManager();
        $product = Product::create([
            'code' => 'SO003', 'name' => '超量测试', 'unit' => '件',
            'purchase_price' => '10.00', 'safety_stock' => 5, 'current_stock' => 10
        ]);

        $response = $this->postJson('/api/stock-outs', [
            'recipient' => '测试部',
            'operator'  => '王五',
            'items'     => [[
                'product_id' => $product->id, 'quantity' => 100, 'unit_price' => '10.00',
            ]],
        ]);

        $response->assertJson(['code' => 1001]);
        $this->assertEquals(10, $product->fresh()->current_stock);
    }

    /**
     * 冲销出库单 → 库存恢复
     */
    public function test_reverse_stock_out_restores_stock(): void
    {
        $this->actingAsManager();
        $product = Product::create([
            'code' => 'SO004', 'name' => '冲销测试', 'unit' => '件',
            'purchase_price' => '10.00', 'safety_stock' => 5, 'current_stock' => 100
        ]);

        // 出库 30
        $create = $this->postJson('/api/stock-outs', [
            'recipient' => 'A部门',
            'operator'  => '赵六',
            'items'     => [[
                'product_id' => $product->id, 'quantity' => 30, 'unit_price' => '10.00',
            ]],
        ]);
        $id = $create->json('data.id');
        $this->assertEquals(70, $product->fresh()->current_stock);

        // 冲销 → 库存恢复
        $this->postJson("/api/stock-outs/{$id}/reverse")
            ->assertStatus(200)
            ->assertJson(['code' => 0]);

        $this->assertEquals(100, $product->fresh()->current_stock);

        // 再次冲销应失败
        $this->postJson("/api/stock-outs/{$id}/reverse")
            ->assertJson(['code' => 1001]);
    }

    /**
     * 多商品出库 — 任意一种库存不足即全部回滚 ⭐ 事务测试
     */
    public function test_stock_out_transaction_rollback_on_any_insufficient(): void
    {
        $this->actingAsManager();
        $productA = Product::create([
            'code' => 'SOA01', 'name' => '充足商品', 'unit' => '件',
            'purchase_price' => '5.00', 'safety_stock' => 5, 'current_stock' => 50
        ]);
        $productB = Product::create([
            'code' => 'SOB01', 'name' => '不足商品', 'unit' => '件',
            'purchase_price' => '3.00', 'safety_stock' => 5, 'current_stock' => 2
        ]);

        $this->postJson('/api/stock-outs', [
            'recipient' => '多商品测试',
            'operator'  => '孙七',
            'items'     => [
                ['product_id' => $productA->id, 'quantity' => 10, 'unit_price' => '5.00'],
                ['product_id' => $productB->id, 'quantity' => 10, 'unit_price' => '3.00'],
            ],
        ]);

        // 事务回滚：两个商品库存都应不变
        $this->assertEquals(50, $productA->fresh()->current_stock);
        $this->assertEquals(2, $productB->fresh()->current_stock);
    }
}
