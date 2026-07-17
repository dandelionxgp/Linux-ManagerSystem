<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InventoryTest extends TestCase
{
    use DatabaseTransactions;

    /**
     * 创建盘点单 → 自动生成明细 + system_qty 快照（按全部商品）
     */
    public function test_create_inventory_snapshots_system_qty(): void
    {
        $this->actingAsManager();
        // 记录盘点前的商品总数
        $countBefore = Product::count();

        // 新增 2 个测试商品
        Product::create([
            'code' => 'INVA' . uniqid(), 'name' => '盘点商品A', 'unit' => '件',
            'safety_stock' => 5, 'current_stock' => 50
        ]);
        Product::create([
            'code' => 'INVB' . uniqid(), 'name' => '盘点商品B', 'unit' => '件',
            'safety_stock' => 5, 'current_stock' => 30
        ]);
        $expectedCount = $countBefore + 2;

        $response = $this->postJson('/api/inventories', ['operator' => '张三']);

        $response->assertStatus(200)
            ->assertJson(['code' => 0, 'message' => '盘点单创建成功']);

        $orderNo = $response->json('data.order_no');
        $this->assertMatchesRegularExpression('/^PD-\d{8}-\d{4}$/', $orderNo);

        $items = $response->json('data.items');
        $this->assertCount($expectedCount, $items);

        // 抽样验证结构
        foreach ($items as $item) {
            $this->assertNull($item['actual_qty']);
            $this->assertEquals(0, $item['diff_qty']);
        }
    }

    /**
     * 按分类创建盘点单 → 只包含该分类的商品
     */
    public function test_create_inventory_by_category(): void
    {
        $this->actingAsManager();
        $category = Category::create(['name' => '食品类' . uniqid()]);
        $code = 'CATFOOD' . uniqid();

        Product::create([
            'code' => $code, 'name' => '特定食品', 'unit' => '件',
            'category_id' => $category->id, 'safety_stock' => 5, 'current_stock' => 20
        ]);

        $response = $this->postJson('/api/inventories', [
            'operator'    => '李四',
            'category_id' => $category->id,
        ]);

        $response->assertStatus(200);
        $items = $response->json('data.items');
        $this->assertGreaterThanOrEqual(1, count($items));

        // 找到我们创建的商品
        $names = array_column(array_column($items, 'product'), 'name');
        $this->assertContains('特定食品', $names);
    }

    /**
     * 录入实盘数量 → diff 自动计算
     */
    public function test_enter_items_calculates_diff(): void
    {
        $this->actingAsManager();
        $code = 'DIFF' . uniqid();
        $product = Product::create([
            'code' => $code, 'name' => '差异测试', 'unit' => '件',
            'safety_stock' => 5, 'current_stock' => 100
        ]);

        // 创建盘点单
        $create = $this->postJson('/api/inventories', ['operator' => '王五']);
        $invId = $create->json('data.id');

        // 录入实盘（系统100，实盘95 → diff = -5）
        $this->putJson("/api/inventories/{$invId}/items", [
            'items' => [[
                'product_id' => $product->id,
                'actual_qty' => 95,
            ]],
        ])->assertStatus(200);

        // 验证差异
        $detail = $this->getJson("/api/inventories/{$invId}");
        $items = $detail->json('data.items');

        // 找到我们的测试项
        $testItem = null;
        foreach ($items as $item) {
            if ($item['product_id'] === $product->id) {
                $testItem = $item;
                break;
            }
        }
        $this->assertNotNull($testItem, '测试商品应在盘点明细中');
        $this->assertEquals(100, $testItem['system_qty']);
        $this->assertEquals(95, $testItem['actual_qty']);
        $this->assertEquals(-5, $testItem['diff_qty']);
    }

    /**
     * 确认盘点 → 盘盈入库 + 盘亏出库 ⭐ 核心测试
     */
    public function test_confirm_adjusts_stock(): void
    {
        $this->actingAsManager();
        $productA = Product::create([
            'code' => 'GAIN' . uniqid(), 'name' => '盘盈商品', 'unit' => '件',
            'purchase_price' => '10.00', 'safety_stock' => 5, 'current_stock' => 50
        ]);
        $productB = Product::create([
            'code' => 'LOSS' . uniqid(), 'name' => '盘亏商品', 'unit' => '件',
            'purchase_price' => '8.00', 'safety_stock' => 5, 'current_stock' => 30
        ]);

        // 创建盘点单
        $create = $this->postJson('/api/inventories', ['operator' => '赵六']);
        $invId = $create->json('data.id');

        // 录入：A 多 10（盘盈）、B 少 5（盘亏）
        $this->putJson("/api/inventories/{$invId}/items", [
            'items' => [
                ['product_id' => $productA->id, 'actual_qty' => 60],
                ['product_id' => $productB->id, 'actual_qty' => 25],
            ],
        ])->assertStatus(200);

        // 确认
        $this->postJson("/api/inventories/{$invId}/confirm")
            ->assertStatus(200)
            ->assertJson(['code' => 0, 'message' => '盘点已确认，库存已自动调整']);

        // 库存：50 + 10 = 60、30 - 5 = 25
        $this->assertEquals(60, $productA->fresh()->current_stock);
        $this->assertEquals(25, $productB->fresh()->current_stock);

        // 盘点单状态 = 3（已确认）
        $detail = $this->getJson("/api/inventories/{$invId}");
        $this->assertEquals(3, $detail->json('data.status'));
        $this->assertNotNull($detail->json('data.confirmed_at'));
    }

    /**
     * 未录入不能确认
     */
    public function test_cannot_confirm_without_entering_items(): void
    {
        $this->actingAsManager();
        Product::create([
            'code' => 'NOCONF' . uniqid(), 'name' => '未录入', 'unit' => '件',
            'safety_stock' => 5, 'current_stock' => 10
        ]);

        $create = $this->postJson('/api/inventories', ['operator' => '孙七']);
        $invId = $create->json('data.id');

        $this->postJson("/api/inventories/{$invId}/confirm")
            ->assertJson(['code' => 1001]);
    }
}
