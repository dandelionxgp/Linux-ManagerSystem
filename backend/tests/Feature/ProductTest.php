<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use DatabaseTransactions;

    /** 记录测试前已有的商品数，用于后续断言 */
    private int $existingCount = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->existingCount = Product::count();
    }

    /**
     * 商品列表分页
     */
    public function test_can_list_products(): void
    {
        $this->actingAsViewer();
        Product::create([
            'code' => 'PT001' . uniqid(), 'name' => '苹果', 'unit' => 'kg', 'safety_stock' => 5
        ]);
        Product::create([
            'code' => 'PT002' . uniqid(), 'name' => '香蕉', 'unit' => 'kg', 'safety_stock' => 5
        ]);
        Product::create([
            'code' => 'PT003' . uniqid(), 'name' => '橙子', 'unit' => 'kg', 'safety_stock' => 5
        ]);

        $response = $this->getJson('/api/products');

        $response->assertStatus(200)
            ->assertJson(['code' => 0]);
        // 已存在 + 新建 = 总数
        $this->assertEquals($this->existingCount + 3, $response->json('data.total'));
    }

    /**
     * 按关键词搜索
     */
    public function test_can_search_products_by_keyword(): void
    {
        $this->actingAsViewer();
        $code = 'SRCH' . uniqid();
        Product::create([
            'code' => $code, 'name' => '独一无二的搜索词', 'unit' => 'kg', 'safety_stock' => 5
        ]);

        $response = $this->getJson('/api/products?keyword=独一无二的搜索词');

        $response->assertStatus(200);
        $this->assertEquals(1, $response->json('data.total'));
    }

    /**
     * 创建商品成功
     */
    public function test_can_create_product(): void
    {
        $this->actingAsManager();
        $code = 'PROD-' . uniqid();

        $response = $this->postJson('/api/products', [
            'code'         => $code,
            'name'         => '测试商品',
            'unit'         => '件',
            'safety_stock' => 20,
        ]);

        $response->assertSuccessful()
            ->assertJson(['code' => 0]);

        $this->assertDatabaseHas('products', ['code' => $code]);
    }

    /**
     * 商品编码唯一性校验
     */
    public function test_cannot_create_duplicate_code(): void
    {
        $this->actingAsManager();
        $code = 'DUP-' . uniqid();
        Product::create([
            'code' => $code, 'name' => 'A', 'unit' => '件', 'safety_stock' => 5
        ]);

        $response = $this->postJson('/api/products', [
            'code' => $code, 'name' => 'B', 'unit' => '件', 'safety_stock' => 5
        ]);

        $response->assertStatus(422);
    }

    /**
     * 更新商品信息
     */
    public function test_can_update_product(): void
    {
        $this->actingAsManager();
        $code = 'UPD-' . uniqid();
        $product = Product::create([
            'code' => $code, 'name' => '原名', 'unit' => '件', 'safety_stock' => 10
        ]);

        $this->putJson("/api/products/{$product->id}", [
            'code' => $code, 'name' => '新名称', 'unit' => '箱', 'safety_stock' => 30
        ])->assertSuccessful();

        $this->assertDatabaseHas('products', [
            'id' => $product->id, 'name' => '新名称', 'unit' => '箱'
        ]);
    }

    /**
     * 软删除无出入库记录的商品
     */
    public function test_can_soft_delete_product(): void
    {
        $this->actingAsManager();
        $code = 'DEL-' . uniqid();
        $product = Product::create([
            'code' => $code, 'name' => '待删', 'unit' => '件', 'safety_stock' => 5
        ]);

        $this->deleteJson("/api/products/{$product->id}")
            ->assertSuccessful();

        $this->assertSoftDeleted('products', ['id' => $product->id]);
    }
}
