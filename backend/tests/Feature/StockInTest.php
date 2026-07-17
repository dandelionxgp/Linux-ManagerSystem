<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class StockInTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 创建入库单 → 返回成功 + 单号格式正确
     */
    public function test_can_create_stock_in(): void
    {
        $this->actingAsManager();
        $product = Product::create([
            'code' => 'SI001', 'name' => '入库测试', 'unit' => '件',
            'purchase_price' => '10.00', 'safety_stock' => 5, 'current_stock' => 0
        ]);

        $response = $this->postJson('/api/stock-ins', [
            'supplier' => '测试供应商',
            'operator' => '张三',
            'items'    => [[
                'product_id' => $product->id,
                'quantity'   => 50,
                'unit_price' => '8.50',
            ]],
        ]);

        $response->assertStatus(200)
            ->assertJson(['code' => 0, 'message' => '入库成功']);

        $orderNo = $response->json('data.order_no');
        $this->assertMatchesRegularExpression('/^RK-\d{8}-\d{4}$/', $orderNo);
    }

    /**
     * 入库后库存增加
     */
    public function test_stock_in_increases_product_stock(): void
    {
        $this->actingAsManager();
        $product = Product::create([
            'code' => 'SI002', 'name' => '库存增加', 'unit' => '件',
            'purchase_price' => '10.00', 'safety_stock' => 5, 'current_stock' => 10
        ]);

        $this->postJson('/api/stock-ins', [
            'operator' => '李四',
            'items'    => [[
                'product_id' => $product->id, 'quantity' => 30, 'unit_price' => '9.00',
            ]],
        ])->assertStatus(200);

        $this->assertEquals(40, $product->fresh()->current_stock);
    }

    /**
     * 冲销入库单 → 库存回退 + 原单状态变更
     */
    public function test_reverse_stock_in_decreases_stock(): void
    {
        $this->actingAsManager();
        $product = Product::create([
            'code' => 'SI004', 'name' => '冲销测试', 'unit' => '件',
            'purchase_price' => '10.00', 'safety_stock' => 5, 'current_stock' => 100
        ]);

        // 入库 20
        $create = $this->postJson('/api/stock-ins', [
            'operator' => '赵六',
            'items'    => [[
                'product_id' => $product->id, 'quantity' => 20, 'unit_price' => '10.00',
            ]],
        ]);
        $id = $create->json('data.id');

        // 冲销
        $this->postJson("/api/stock-ins/{$id}/reverse")
            ->assertStatus(200)
            ->assertJson(['code' => 0, 'message' => '入库单已冲销']);

        // 库存回退（100 + 20 入库 - 20 冲销 = 100）
        $this->assertEquals(100, $product->fresh()->current_stock);

        // 再次冲销应失败
        $this->postJson("/api/stock-ins/{$id}/reverse")
            ->assertJson(['code' => 1001]);
    }

    /**
     * viewer 不能操作入库
     */
    public function test_viewer_cannot_stock_in(): void
    {
        $this->actingAsViewer();
        $product = Product::create([
            'code' => 'SI005', 'name' => '权限测试', 'unit' => '件',
            'safety_stock' => 5, 'current_stock' => 0
        ]);

        $response = $this->postJson('/api/stock-ins', [
            'operator' => 'test',
            'items'    => [[
                'product_id' => $product->id, 'quantity' => 10, 'unit_price' => '1.00',
            ]],
        ]);

        $response->assertStatus(403);
    }
}
